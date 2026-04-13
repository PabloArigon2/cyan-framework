# Plano de Refatoração e Desacoplamento do Framework CyanV1

Este documento descreve o plano de ação para tornar o framework CyanV1 completamente genérico, removendo quaisquer amarras, classes ou variáveis que sejam específicas de regras de negócio de um projeto particular (como sistema de saúde, cuidadores, clínicas ou gateway de pagamento fixo).

## User Review Required

> [!IMPORTANT]
> **Aprovação Necessária**
> Revise as classes e lógicas que serão REMOVIDAS do `builder.php` (ex: `Profissao`, `Cliente`, `CadEmpresa`). Em um framework genérico, essas entidades devem ser herdadas ou criadas na própria aplicação final (o projeto) e não instanciadas na base do framework. Após minha refatoração, seu projeto antigo poderá precisar dessas classes implementadas do lado dele para voltar a compilar.
> Além disso, o módulo financeiro (Asaas) será encapsulado/desacoplado do `main.php`.

## Proposed Changes

### Core Elements & Initialization

#### Internacionalização de Código (English Only) e Módulo Locale
- Para garantir que o CyanV1 alcance os requisitos globais e de pacotes profissionais via Composer, **todas as propriedades, métodos estáticos e variáveis de espinha dorsal serão traduzidas e elaboradas em Inglês**. As funções nativas de PT-BR (ex: `removerAcentos`, `calcularAnos`, `Acao`) se tornarão `removeAccents()`, `calculateYears()`, e `Action/Request`, assim como o painel do novo Firewall usará `getRejectedRequests()`.
- **Módulo de Locale/Translation:** Como o código-fonte operará nativamente em Inglês, criaremos um utilitário de Idioma (`locale.php` ou `Translation`) utilizando o padrão simples de Chave/Valor (ex: `__('http.errors.unauthorized')`). Isso fará com que as respostas dinâmicas disparadas automaticamente pelo framework (como "Token Inválido" ou "Rate Limit Excedido") não sejam engessadas e respondam na língua definida no arquivo de ambiente de cada projeto.

#### [NEW] [composer.json] (Dependency Management)
- **Instalador Nativo e DotEnv:** Em vez de criarmos "na unha" um parser de `.env` que quebra com aspas complexas, iniciaremos a padronização oficial do pacote exigindo as bibliotecas base: `vlucas/phpdotenv` (Para parseamento blindado de variáveis de ambiente do DB e API) e geraremos o mapa canônico do PSR-4 Autoloading.

#### [MODIFY] [main.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/main.php)
- **Migração para Autoloader PSR-4:** O `main.php` atua como um enorme índice de `require_once` manuais. Como o framework migrará para ecossistema Composer, todo esse bloco será descontinuado e usaremos apenas um enxuto `require "vendor/autoload.php"`.
- **Configuração Agnóstica de Ambiente:** Identificamos que a data (`America/Bahia`) fica rígida no código. Ela será alimentada de forma segura pelo DotEnv (`env('APP_TIMEZONE', 'UTC')`).
- **Remoção de Poluição Global:** Há um grande Array estático chamado `$ufs` jogando lixo na memória. Ele e a classe `JSON` e `HttpCode` serão deslocados para módulos dedicados (dentro de `structs.php`).
- **Limpeza de Código Morto:** A função `GenerateSessionToken()` e sua forte dependência em SQL (`SELECT id FROM usuarios`) serão inteiramente deletadas por obsolescência.
- Refatorar a classe `UserType`. Os papéis `CLIENTE`, `UNIDADE`, `PROFISSIONAL`, `PACIENTE` são específicos de um projeto e migrarão para implementações fora do core.
- Remover a obrigatoriedade do arquivo `pagamentos.php` na inicialização (`require_once "pagamentos.php";`), transformando-o num módulo acessível sob demanda.

#### [MODIFY] [structs.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/structs.php)
- Centralização de Enums/Structs: Atualmente existem diversas classes com padrão de Struct (ex: `final class HttpCode` no `main.php`, `final class Pessoa` no `builder.php`, etc) perdidas e soltas pelos arquivos do projeto. Todas elas serão realocadas para o `structs.php`, mantendo o código organizado e preservando um repositório centralizado de constantes puras do sistema.

---

### Storage e Gestão de Uploads

#### [NEW] [storage.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/storage.php)
- **Módulo Dedicado:** A arquitetura do *FileTransactionManager*, `Payload` e `FileIntent` (que hoje suja o `builder.php` a partir da linha 560 em diante) será extraída para este arquivo especializado em I/O.
- **Prevenção de Extension Spoofing:** Na aplicação atual (metódo `save` e `apply`), a segurança depende exclusivamente de um vetor do array `$info['extension']` (ex: bloquear o `.exe` baseado em nome). Essa prática falha completamente se um hacker envelopar um shell `.php` num `.png` forjando o nome. Como contenção, alteraremos o código para varrer nativamente o arquivo físico via **finfo_file** (`FILEINFO_MIME_TYPE`), decifrando a assinatura de Byte Mime-Type inviolável.
- **Redundância:** Caso a extensão PECL do `finfo` não esteja ativada no host da sua aplicação por limitação de Cpanel, o sistema fará Fallback acionando o clássico `mime_content_type()` para provar o *MIME Type*, recusando a compilação do upload de qualquer forja.
- **Sandboxed File Streamer:** Para neutralizar totalmente qualquer tentativa de execução maliciosa (ex: Arquivo com XSS ou um shell injetado num falso `.html`/`.svg`), criaremos uma função gotejadora (Dispatcher). Esse despachante usará `readfile()` do PHP e forçará Headers ultra-rígidos (`Content-Security-Policy: sandbox; default-src 'none'`) e ignorará extensões simuladas. Com isso, seja gerando uma URL na API ou devolvendo na tela direta, o navegador do usuário exibirá a mídia de forma desidratada/inativa, erradicando os riscos de infecção no lado do cliente ou servidor.

---

### Request Handling & Action Routes

#### [MODIFY] [actions.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/actions.php)
- **Refatoração para Singleton (Orientação a Objetos):** Atualmente o sistema roda preenchendo dezenas de variáveis globais na memória (`$acao`, `$id`, `$method`, `$dados_json`) e injetando elas destrutivamente via declarações `global $acao;`. Isso será refatorado e toda a abstração de requisição se tornará uma classe base `Request` ou a transição para um Handler estático.
- **Remoção das Amarras de Domínio:** Em `$availableArgs`, o framework mapeava regras de negócio (`"cliente" => $id`, `"paciente" => $id`, etc). Removeremos os aliases de projeto, mantendo parâmetros genéricos de Injeção de Dependências.
- **Risco de Segurança (Hardcoded Auth-Bypass):** A segurança será trocada para um padrão moderno: O método `Action::Create` exigirá Token Seguro nativamente, acompanhado do irmão limpo `Action::CreatePublic` para rotas isentas.
- **Vazamento de Log (Data Exposure):** A nova versão permitirá mascarar campos sensíveis (`senha`, `cc`) para blindar os logs nativos do `Action::Run()`.
- **Controlador Central de CORS (Pre-Flight Middleware):** Para dar compatibilidade total aos frontends apartados (ex: Vue, Next.Js, React Mobile), o roteador fará a injeção estanque e controlável dos Headers de CORS (`Access-Control-Allow-Origin`, `Methods`, etc) neutralizando erros de Pre-Flight (OPTIONS) ao desenvolver.
- **Lifecycle e Error Handlers:** Injetaremos Handlers Globais de Exceção (`set_error_handler` e `set_exception_handler`) lidando primorosamente com interrupções anormais *antes* que o motor crashe o CORS ou lance fatal errors visíveis.

---

### Builder & Entities

#### [MODIFY] [builder.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/builder.php)
- **Remover** as classes de domínio específico que não fazem parte do escopo de um framework de base: 
   - `Profissao` (com constantes ligadas à área da saúde: Medico, Fisioterapeuta, etc).
   - `CadEmpresa` (especializado sob medida).
   - `Cliente` (possui dependências com campos `CNES`, `Financeiro`, etc).
- Ajustar `TokenEnv` para mudar o ambiente `EMPRESA` para algo genérico de sistema multi-tenant, como `TENANT` ou `ORGANIZATION`.
- Em `$Context`, ajustar chaves se aplicável, mantendo a abstração universal.

---

### Security & Utilities

#### [MODIFY] [permissions.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/permissions.php)
- O escopo interno e as matrizes `$Perms` e `$Array` serão eliminadas (Framework não deve ter os nomes finais das permissões hardcoded). Em vez disso, usaremos um `PermissionsRegistry` onde a aplicação pode registrar suas chaves.
- **Correção de Vulnerabilidade Multi-Tenant e Escopo Misto:** O sistema lidará com dois escopos: **Tenants** e **Sistema Administrador (Global)**. Atualmente `CreateGroup` e `UpdateGroup` garantem unicidade global pelo "nome", impedindo que duas empresas criem o grupo "Admin". Atualizaremos as Queries para validar o nome restrito pelo ambiente (Contexto). Se o contexto de origem for o de Administração do Sistema (parent `0` ou `System`), os grupos e permissões serão validados globalmente. Da mesma forma, `HasPermission($user, $perm, $context_id)` será rigoroso, validando não apenas se a permissão existe, mas garantindo que o escopo dessa permissão (seja ela global ou delegada por tenant) coincida com o ambiente acessado, travando o Escalonamento de Privilégios (Privilege Escalation).
- **Otimização de Performance (Memory Cache):** Atualmente, buscar se um usuário possui uma permissão requer bater no SQL `grupos_usuario`, puxar o Payload, realizar o `Decrypt()` (AES de alta latência), realizar o `json_decode`, para então checar a chave. Se num requisição você chegar 5 permissões diferentes, tudo isso repete 5 vezes. Vou implementar um `Cache Estático de Ciclo de Vida` na classe para que ele vá ao banco apenas 1 vez por requisição REST, memorizando a árvore de permissões na memória RAM local durante a Request.
- **Debate Estrutural:** Sugerirei a remoção da criptografia (AES) da coluna de `permissions` da tabela `grupos`. Permissões em aplicações SaaS quase nunca são dados PII ou críticos ao ponto de justificar o custo gigantesco de AES em toda Rota Verificadora Auth. Um dado `{"pode_editar": true}` em texto puro facilita queries via `LIKE` ou `JSON_CONTAINS` no SQL sem sacrificar a segurança real.

### Utilities & Global Scope (Preparação para Composer)

#### [MODIFY] [logs.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/logs.php)
- O auto-logging será 100% mantido operando em simbiose com o Roteador, com o conceito genial de 2-Steps (faz o *Insert* da Action antes de rodá-la, e depois dá *Update* no mesmo ID com o response final, permitindo rastrear onde parou em caso de Fatal Error).
- **Remoção de Globais:** A variável rústica `global $current_log_id` será removida e encapsulada como propriedade estática nativa da classe `Logs::$currentLogId`, limpando o escopo.
- **Scrubbing de Dados Sensíveis:** Atualmente, a função `setBody()` salva tudo cegamente. Construiremos nela um Mascarador de JSON. Se arrays contiverem as propriedades `[ 'senha', 'password', 'token', 'cvv' ]`, a classe automaticamente substituirá o valor por `***` (Scrub) antes de dar o `json_encode` para salvar no banco. 
- **Tratamento de Exceção Silencioso:** Atualmente, se o banco de dados falhar ao salvar um log, a linha 168 dispara um `echo $data->error();`. Sendo um framework profissional, os logs que falham jamais podem "vazar" o erro SQL na tela do usuário. O erro deve prosseguir silenciosamente ou ser gravado em um arquivo físico `.log` local (fallback).
- **Adequação do Audit (LGPD/GDPR):** Na função estática `audit()`, os endereços de IP (`REMOTE_ADDR`) estão sendo armazenados via `Encrypt()`. Apesar de seguro, isso impede o sistema de fazer buscas analíticas para bloquear ataques DDOS (pois você não pode buscar num LIKE uma string AES cifrada). Sugiro armazenar o IP Criptografado, mas adicionar também uma coluna na tabela salvando ele como **Blind Index** (`DBHash($ip)`), o que permite o banco de dados rastrear IPs únicos rapidamente na sua dashboard de Auditoria sem revelar o IP nativo.

#### [MODIFY] [utils.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/utils.php) & Global Functions
- Como o framework será distribuído via Composer, **não podemos ter funções soltas no escopo global** (ex: `env()`, `getCallback()`, `loadJson()`).
- Todas as funções avulsas nas raízes dos arquivos (`main.php`, `actions.php`, `logs.php`, `database.php`) serão movidas para classes estáticas (ex: `System::env()`, `ActionHelper::parse_formdata()`).
- Refatoração dos nomes de escopo: A classe verificadora de escopos de requisição (inicialmente chamada de `Tenant` no `builder.php` e utilizada no `actions.php`) será renomeada para **`Context`**. Já as referências diretas de regra de negócio à entidade "Empresa" (ex: `$id_empresa`, `GetEmpresaToken()`) passarão a se chamar universalmente **`Tenant`**.


#### [NEW] [formatter.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/formatter.php)
- Criação de uma nova classe agregadora de utilitários visuais e de texto, como `Formatter`.
- Funções atreladas à exibição (ex: `removerAcentos`, `removeSlugs`, `formatName`, `formatValor`, `formatarDuracao`) sairão do `utils.php` e virão para cá, dentro da classe estática `Formatter`.

#### [DELETE] [info.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/info.php) e Arquivos Vazios
- Como parte do refatoramento voltado para padronizar e publicar, arquivos residuais de desenvolvimento ou completamente vazios (ex: `info.php`, que identifiquei ter apenas 15 bytes com tags `<?php ?>`) serão permanentemente removidos.

---

### Database & Security

#### [NEW] [session.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/session.php)
- Criação de uma classe robusta para gerenciamento de Sessão do lado do servidor (`Session`).
- Será responsável por aplicar as configurações de segurança automáticas de cookies (`httponly`, `samesite`, `lifetime`), inicialização limpa (`session_start` e `session_regenerate_id`) e getters/setters tipados para os objetos salvos na sessão (como o Fingerprint do navegador e os arrays de `user` e `context`).

#### [MODIFY] [cryptography.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/cryptography.php)
- O código de criptografia atual é excelente (AES-256-GCM + Argon2id), mas refatoraremos a classe `Security` introduzindo o conceito de separação de chaves (Key Separation).
- A chave-mestra atual será passada pela função nativa `hash_hkdf()` para gerar nativamente duas sub-chaves distintas a fim de mitigar choques paralelos: uma sub-chave restrita apenas para Busca (Blind Indexes/Hash HMAC) e outra completamente isolada apenas para Encriptação e Decriptação simétrica (AES).

#### [NEW] [qb.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/qb.php)
- Criação de uma classe opcional de construção de Query (`QB` - Query Builder) que constrói SQL de forma segura, com suporte nativo à inclusão automática de propriedades de isolamento (ex: inserindo silenciosamente `WHERE tenant_id = ?`).
- Nenhuma alteração nas assinaturas atuais da antiga classe `Database` (`database.php`) ocorrerá, preservando toda compatibilidade. Apenas adicionaremos as funções utilitárias que permitem usar validação (`Context::VerifyTenantAccess()`).

### Security & Traffic Management (Firewall/Rate Limiting)

#### [NEW] [firewall.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/firewall.php) E Antigos Security/CSRF
- Criação de um módulo robusto de defesa de ponta (`Firewall`) focado no controle de fluxo e mitigação L7 de camada de aplicação.
- **Integração com o Roteador:** Esse módulo ficará plugado como um *middleware* nativo em `Action::Create`. Ele validará automaticamente *Rate Limiting* (Requisições por segundo) e Timeouts contra *Brute-Force* sem exigir código adicional por parte dos desenvolvedores.
- **Modernização do CSRF:** O arquivo `csrf.php` atual apenas cospe strings HTML (`<input type="hidden">`), o que o torna inútil para arquiteturas API REST SPA (Vue/React). Refatoraremos o CSRF para aceitar Cabeçalhos (`X-CSRF-TOKEN`) para total compatibilidade Frontend-Backend moderna.
- **Merge de Session:** Descobrimos que o projeto já possuía o `security.php` com a abstração `SecureSession`. Unificaremos esse arquivo no escopo definido da nova classe de Sessões (`Session`), herdando suas validações de Timeout e User-Agent.

---

### Database Core

#### [MODIFY] [database.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/database.php) e [cryptography.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/cryptography.php)
- **Remoção de Hardcode (Coupling):** O mecanismo de inicialização atual (`develop.ecoglobal.com.br`) será expurgado e a classe alimentada por `.env`.
- **Helpers de Reversão e Injeção de Criptografia (DX):** Para abolir linhas repetitivas nas rotas, o framework terá envelopadores ágeis com múltiplas redundâncias atendendo tanto Decriptação quanto Encriptação:
  - Uma função expressa (`$qr->getDecrypted()`) acoplada na camada de banco para baixar dados decifrados.
  - Funções de extração explícita no utilitário de Criptografia: Uma passando parâmetros primordiais (`DecryptByIdentifier($data, $id, $identifier, $env)`) e outra via Token livre (`DecryptByToken($data, $token)`).
  - Funções de injeção explícita no utilitário de Criptografia: `EncryptByIdentifier($data, $id, $identifier, $env)` e `EncryptByToken($data, $token)`.
  - **Auto-JSON Detection e Parsing:** Todas as vias de mão dupla (Encrypt/Decrypt) avaliarão inteligentemente as Strings e Arrays. O descompactador usará `json_validate()` para devolver nativamente arrays PHP invés de string crua, enquanto o encriptador fará automaticamente o `json_encode` caso um Array ou Objeto seja passado para dentro das funções.

---

### Financeiro e Terceiros

#### [DELETE] [pagamentos.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/pagamentos.php)
#### [NEW] [Modules/Payment/Asaas.php](file:///d:/Main%20Folder/Projetos/Frameworks/CyanV1/Modules/Payment/Asaas.php)
- O arquivo na raiz será deletado e seu conteúdo isolado da inicialização padrão (tirar `require "pagamentos.php"` do `main.php`).
- A lógica será inteiramente movida para o uso como Módulo opcional (`Modules/Payment/Asaas.php`).


## Verification Plan

### Automated Tests/Verifications
- Analisarei todos os imports (`require_once`) para garantir que o projeto compila sem os hardcodes removidos.
- Testarei internamente (via linha de comando, executando um simples `php main.php`) para não ocorrer problemas de Sintaxe (Syntax Error).

### Manual Verification
- Ao final, o usuário precisará importar o CyanV1 limpo num de seus projetos e corrigir os repositórios/classes faltantes lá e confirmar que o core volta a iniciar perfeitamente.
