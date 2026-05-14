# Walkthrough: Sistema de Filas (Queue) com Redis

O sistema de processamento de filas assíncronas utilizando Redis foi implementado com sucesso no framework CyanV1. Abaixo está o resumo de como ele foi construído e como utilizá-lo.

## O que foi alterado

- **Interface `IJob`:** Adicionada em [structs.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/src/structs.php). Todos os jobs devem implementar esta interface contendo o método `handle(array $data)`.
- **Classe `Queue`:** Criado o arquivo [queue.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/src/queue.php) com todos os métodos necessários para gerenciar e executar as filas.

## Como utilizar

### 1. Criando um Job

Crie uma classe que implementa `IJob`. O método `handle` é onde você colocará o código demorado/pesado.

```php
class EnviarEmailJob implements IJob {
    public function handle(array $data) {
        $destinatario = $data['to'];
        $mensagem = $data['msg'];
        
        // Simular envio
        sleep(2);
        
        // Se houver algum erro, basta lançar uma Exception e 
        // o Queue moverá esse job para a fila de falhas
        if (empty($destinatario)) {
            throw new Exception("Destinatário em branco!");
        }
    }
}
```

### 2. Adicionando Jobs à Fila (via API)

Para evitar sobrecarregar a API, em vez de executar o código diretamente, você empurra o job para o Redis:

```php
$queue = new Queue([
    'host' => '127.0.0.1', 
    'port' => 6379
]);

// Adiciona na fila "emails"
$queue->push('emails', EnviarEmailJob::class, [
    'to' => 'cliente@exemplo.com',
    'msg' => 'Bem-vindo ao sistema!'
]);
```

### 3. Rodando o Worker (Terminal/Cron/Supervisor)

Em um arquivo separado (por exemplo `worker.php`), você vai iniciar o Worker que ficará lendo o Redis e processando.

```php
require_once "caminho/para/CyanV1/src/startup.php";
Startup::Module("queue");
// Carregar outras dependências e o job...

$queue = new Queue();

// Fica processando a fila de "emails" eternamente
$queue->work('emails'); 
```
*Configure o `supervisor` ou `systemd` para manter este script rodando no terminal (ex: `php worker.php`).*

### 4. Gerenciamento e Getters

Conforme solicitado, a classe inclui métodos para inspecionar e controlar as filas. Você pode criar um painel de administração que consome esses métodos:

```php
$queue = new Queue();

// 1. Obtendo o status geral da fila
$status = $queue->status('emails');
// Resultado: ['name' => 'emails', 'pending_count' => 10, 'failed_count' => 2, 'is_paused' => false]

// 2. Obtendo jobs pendentes e os que falharam
$pendentes = $queue->getJobs('emails'); 
$falhados = $queue->getFailedJobs('emails'); 

// 3. Pausar e Retomar a fila
$queue->pause('emails'); // O worker vai parar de puxar novos itens
$queue->resume('emails');

// 4. Retentar os que falharam
$queue->retryFailed('emails'); // Move os falhados de volta pra fila principal

// 5. Limpar fila
$queue->clearFailed('emails');
$queue->clear('emails');
```
