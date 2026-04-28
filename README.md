# Cyan Framework V1

**Cyan Framework V1** é um framework PHP robusto, modular e privado, desenvolvido inteiramente para uso pessoal e projetos internos.

> ⚠️ **Aviso de Licença e Uso:**
> Este é um projeto **estritamente privado e proprietário**. Seu código não possui código aberto, não está sob licença pública (como MIT ou GPL) e **não há direitos para uso, distribuição ou modificação pública**. O uso deste framework é exclusivo e restrito ao seu criador (@pabloarigon2).

## Requisitos

- **PHP:** ^8.2
- **Dependências:**
  - `vlucas/phpdotenv` (^5.6)
  - `ext-redis` (Extensão do Redis para cache)

## Estrutura e Módulos Principais

O framework foi construído pensando em cobrir todas as necessidades básicas e avançadas de uma aplicação web moderna, operando inclusive com forte integração ao modelo Single Page Application (SPA).

Abaixo estão as principais categorias de ferramentas e módulos disponíveis no `src/`:

### 🛡️ Segurança & Autenticação
- **`auth.php`**: Sistema de login, validação e gerência de usuários.
- **`firewall.php`**: Regras de proteção, bloqueio de requisições maliciosas e segurança da aplicação.
- **`cryptography.php`**: Ferramentas para criptografia avançada de dados.
- **`csrf.php`**: Proteção contra falsificação de solicitações entre sites.
- **`permissions.php`**: Controle de acesso e nível de permissões de usuários.
- **`validate.php`**: Sanitização e validação estrita de dados de entrada.

### 💾 Banco de Dados & Armazenamento
- **`database.php` & `qb.php`**: Camada de abstração de banco de dados robusta com Query Builder integrado, suportando logs transacionais.
- **`cache.php`**: Sistema avançado de cache multi-driver (Memória, Arquivo, Redis) com suporte para criptografia.
- **`storage.php`**: Manipulação e armazenamento de arquivos e diretórios.

### 🌐 Roteamento & HTTP
- **`router.php`**: Sistema de rotas dinâmico desenhado especificamente para suportar comportamentos SPA (Single Page Application).
- **`http.php` & `response.php`**: Manipulação requisições HTTP, respostas JSON estruturadas, headers e status codes.
- **`session.php`**: Gestão customizada e segura das sessões dos usuários.

### 🛠️ Utilitários & Core
- **`builder.php`**: Ferramentas e utilitários auxiliares (Builder pattern) do sistema.
- **`logs.php`**: Sistema de logs persistente com rastreabilidade detalhada, resistente a falhas de transação no banco.
- **`env.php` & `config.php`**: Gerenciamento de configurações e variáveis de ambiente usando Dotenv.
- **`helpers.php` & `utils.php`**: Coleções extensas de funções genéricas de auxílio.
- **`formatter.php` & `str.php`**: Formatação e processamento otimizado de strings e dados.
- **`locale.php` & `lang/`**: Sistema completo de tradução e localização.
- **`geodata.php`**: Funções relacionadas a localização e dados geográficos.
- **`math.php`**: Ferramentas auxiliares matemáticas.
---
*Desenvolvido por Pablo Arigon - Uso estritamente privado.*
