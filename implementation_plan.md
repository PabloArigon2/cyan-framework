# Implementação do Sistema de Queue com Redis

O objetivo é adicionar um sistema de filas completo ao framework CyanV1 utilizando Redis. Isso permitirá adicionar trabalhos (jobs) em uma fila durante chamadas de API rápidas, deixando o processamento pesado para workers assíncronos. Além disso, o sistema deve suportar falhas, enviando os jobs que derem erro para uma "fila de falhas/pausa" para reprocessamento futuro.

## User Review Required

> [!IMPORTANT]
> **Serialização de Jobs**: No PHP puro, não é possível serializar "Closures" (funções anônimas) nativamente. A abordagem recomendada para jobs é criar classes que implementem um contrato (ex: `IJob`) com um método `handle()`, ou passar o nome de uma função global/método estático. Você concorda com essa limitação/padrão?

> [!IMPORTANT]
> **Execução do Worker**: Para que a fila seja processada, você precisará rodar um script PHP no terminal de forma contínua (ex: `php worker.php`) que chamará o método `Queue::work('nome_da_fila')`. Você possui acesso ao terminal do servidor para configurar um serviço (como Supervisor ou Systemd) para manter esse worker rodando?

## Proposed Changes

### Cyan-Framework

#### [NEW] [queue.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/src/queue.php)
Será criado o arquivo `queue.php` na raiz do `src/` com a classe `Queue`.
Principais funcionalidades:
- **Conexão Redis**: Estabelece conexão direta via classe `Redis` (extensão php-redis).
- **`push(string $queue, string $jobClass, array $data = [])`**: Adiciona o job à fila especificada no final da lista (`rPush`).
- **`work(string $queue)`**: Método que roda em loop. Fica monitorando a fila (usando `blPop` para evitar alto uso de CPU). Ao receber um job, instancia a classe e chama o método `handle()`.
- **Controle de Pausa**: Verifica uma chave no Redis `queue:$queue:paused`. Se existir e for verdadeira, o worker dorme e não retira novos itens até ser despausado.
- **Fila de Falhas**: Se a execução do `handle()` gerar uma exceção, o job é movido para a fila `queue:$queue:failed`, juntamente com os detalhes do erro, horário e payload original.
- **`pause(string $queue)` / `resume(string $queue)`**: Métodos para o painel de administração pausar ou retomar o processamento de uma fila.
- **`retryFailed(string $queue)`**: Move os jobs da fila de falha de volta para a fila principal para tentar novamente.

#### [NEW] [IJob.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/src/structs.php) (ou no arquivo atual de structs)
Vamos definir uma interface no arquivo `structs.php` (ou criar um novo se preferir manter separado) chamada `IJob`:
```php
interface IJob {
    public function handle(array $data);
}
```

## Verification Plan

### Automated Tests
1. **Push**: Criar um script temporário na API que chame `Queue::push('emails', 'SendEmailJob', ['to' => 'teste@teste.com'])`.
2. **Worker**: Criar um arquivo `worker_test.php` que chama `Queue::work('emails')`.
3. **Fail/Pause**: Simular um erro dentro do `SendEmailJob` e verificar se ele cai na fila de falhas. Pausar a fila e verificar se o worker para de processar.

### Manual Verification
1. Analisar as chaves geradas no servidor Redis usando a ferramenta `redis-cli` (comandos como `KEYS *` e `LRANGE queue:emails 0 -1`).
2. Discutiremos se será necessário integrar um painel/dashboard básico posteriormente, mas o core já deve permitir o gerenciamento pelas funções `Queue::retryFailed()` e etc.
