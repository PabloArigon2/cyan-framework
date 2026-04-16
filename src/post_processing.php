<?php

final class PostProcessing {

    private static array $tasks = [];
    private static bool $registered = false;

    /**
     * Registra uma tarefa para rodar após a resposta ser enviada ao cliente.
     * $priority: menor número = roda primeiro
     */
    public static function register(callable $task, int $priority = 10): void {
        self::$tasks[] = ['fn' => $task, 'priority' => $priority];

        if (!self::$registered) {
            self::$registered = true;
            register_shutdown_function([self::class, 'run']);
        }
    }

    /**
     * Chamado automaticamente no shutdown — não chamar manualmente.
     */
    public static function run(): void {
        if (empty(self::$tasks)) return;

        usort(self::$tasks, fn($a, $b) => $a['priority'] <=> $b['priority']);

        foreach (self::$tasks as $task) {
            try {
                ($task['fn'])();
            } catch (Throwable $e) {
                error_log('[PostProcessing] ' . $e->getMessage());
            }
        }

        self::$tasks = [];
    }
}

?>