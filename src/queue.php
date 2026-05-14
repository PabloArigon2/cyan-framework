<?php

class Queue {
    private Redis $redis;
    private string $prefix = 'queue:';

    public function __construct(array $config = []) {
        if (!class_exists('Redis')) {
            throw new Exception("Extensão Redis não encontrada.");
        }

        $this->redis = new Redis();
        try {
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 6379;
            $this->redis->connect($host, $port);
            
            if (!empty($config['password'])) {
                $this->redis->auth($config['password']);
            }
            
            $this->redis->select((int)(123));
        } catch (Exception $ex) {
            throw new Exception("Queue Connection failed: {$ex->getMessage()}", 1);
        }
    }

    private function getQueueName(string $queue): string {
        return $this->prefix . $queue;
    }

    private function getFailedQueueName(string $queue): string {
        return $this->prefix . $queue . ':failed';
    }

    private function getPauseKey(string $queue): string {
        return $this->prefix . $queue . ':paused';
    }

    /**
     * Adiciona um job na fila.
     * 
     * @param string $queue Nome da fila
     * @param string $jobClass Nome da classe que implementa IJob
     * @param array $data Dados que serão passados para o método handle()
     * @return bool
     */
    public function add(string $queue, string $jobClass, array $data = []): bool {
        $payload = json_encode([
            'id' => uniqid('job_', true),
            'class' => $jobClass,
            'data' => $data,
            'pushed_at' => time()
        ], JSON_UNESCAPED_UNICODE);

        // lPush adiciona no começo da lista. brPop vai tirar do final.
        return $this->redis->lPush($this->getQueueName($queue), $payload) > 0;
    }

    /**
     * Processa a fila de forma contínua (Worker).
     * 
     * @param string $queue Nome da fila
     * @param int $timeout Tempo máximo em segundos para esperar por um novo job (0 = bloqueio contínuo)
     */
    public function run(string $queue, int $timeout = 0): void {
        $qName = $this->getQueueName($queue);
        $pauseKey = $this->getPauseKey($queue);

        // Se timeout for 0, usamos um timeout baixo no brPop para poder verificar o pauseKey frequentemente
        $popTimeout = $timeout > 0 ? $timeout : 2;

        while (true) {
            // Verifica se a fila está pausada
            if ($this->redis->get($pauseKey)) {
                sleep(2);
                continue;
            }

            // Pega o item do final da lista (FIFO)
            $result = $this->redis->brPop($qName, $popTimeout);

            if (empty($result)) {
                // Se timeout > 0 e não encontrou nada no tempo determinado, sai do loop (útil para CRONs pontuais)
                if ($timeout > 0) {
                    break;
                }
                continue;
            }

            // brPop retorna array [nome_da_lista, valor]
            $payload = $result[1];
            $this->processPayload($queue, $payload);
        }
    }

    /**
     * Processa um único item e executa o job
     */
    private function processPayload(string $queue, string $payload): void {
        $jobData = json_decode($payload, true);

        if (!$jobData || !isset($jobData['class'])) {
            $this->fail($queue, $payload, new Exception("Formato de payload inválido."));
            return;
        }

        try {
            $class = $jobData['class'];
            if (!class_exists($class)) {
                throw new Exception("Classe do Job '$class' não encontrada.");
            }

            $jobInstance = new $class();
            if (!$jobInstance instanceof IJob) {
                throw new Exception("Classe '$class' deve implementar a interface IJob.");
            }

            // Executa o job passando os dados
            $jobInstance->handle($jobData['data'] ?? []);

        } catch (\Throwable $e) {
            $this->fail($queue, $payload, $e);
        }
    }

    /**
     * Move um job para a fila de falhas
     */
    private function fail(string $queue, string $payload, \Throwable $e): void {
        $jobData = json_decode($payload, true);
        if (!$jobData) {
            $jobData = ['raw' => $payload];
        }

        $failedPayload = json_encode([
            'job' => $jobData,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'failed_at' => time()
        ], JSON_UNESCAPED_UNICODE);

        // Adiciona à fila de falhas
        $this->redis->lPush($this->getFailedQueueName($queue), $failedPayload);
    }

    // --- GETTERS (ESTADOS E ITENS) ---

    /**
     * Retorna o status da fila.
     */
    public function status(string $queue): array {
        return [
            'name' => $queue,
            'pending_count' => $this->redis->lLen($this->getQueueName($queue)),
            'failed_count' => $this->redis->lLen($this->getFailedQueueName($queue)),
            'is_paused' => (bool)$this->redis->get($this->getPauseKey($queue))
        ];
    }

    /**
     * Retorna os jobs pendentes na fila.
     */
    public function getJobs(string $queue, int $start = 0, int $end = -1): array {
        $items = $this->redis->lRange($this->getQueueName($queue), $start, $end);
        return array_map(function($item) {
            return json_decode($item, true);
        }, $items);
    }

    /**
     * Retorna os jobs que falharam.
     */
    public function getFailedJobs(string $queue, int $start = 0, int $end = -1): array {
        $items = $this->redis->lRange($this->getFailedQueueName($queue), $start, $end);
        return array_map(function($item) {
            return json_decode($item, true);
        }, $items);
    }

    // --- CONTROLES DA FILA ---

    /**
     * Pausa o processamento da fila.
     */
    public function pause(string $queue): bool {
        return $this->redis->set($this->getPauseKey($queue), '1');
    }

    /**
     * Retoma o processamento da fila.
     */
    public function resume(string $queue): bool {
        return $this->redis->del($this->getPauseKey($queue)) > 0;
    }

    /**
     * Move todos os jobs que falharam de volta para a fila principal.
     */
    public function retryFailed(string $queue): int {
        $failedQ = $this->getFailedQueueName($queue);
        $mainQ = $this->getQueueName($queue);
        $count = 0;

        // Pega do final da fila de falhas e reinsere no começo da fila principal
        while ($payload = $this->redis->rPop($failedQ)) {
            $data = json_decode($payload, true);
            $originalPayload = json_encode($data['job'], JSON_UNESCAPED_UNICODE);
            $this->redis->lPush($mainQ, $originalPayload);
            $count++;
        }

        return $count;
    }
    
    /**
     * Limpa a fila de falhas.
     */
    public function clearFailed(string $queue): bool {
        return $this->redis->del($this->getFailedQueueName($queue)) > 0;
    }
    
    /**
     * Esvazia a fila principal.
     */
    public function clear(string $queue): bool {
        return $this->redis->del($this->getQueueName($queue)) > 0;
    }
}
?>
