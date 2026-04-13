# Enciclopédia Técnica do CyanV1: Guia Definitivo de Arquitetura

Este documento foi elaborado para garantir sua total autonomia técnica sobre o projeto. Um framework complexo exige que o engenheiro saiba *por que* algo foi implementado, para que ele possa dar manutenção sem depender cegamente do código escrito. 

Abaixo, dissecamos todos os conceitos de ponta integrados ao **CyanV1**.

---

## 1. CORS (Cross-Origin Resource Sharing)

### O que é?
CORS é uma política de segurança nativa de todos os navegadores reais (Chrome, Safari, Firefox). Ela dita que, se o seu site local `http://meu-front.com` tentar bater num endpoint remoto `https://api.cyan.com`, o navegador abortará a conexão a menos que a API remota autorize.

### O Problema do "Pre-Flight" (OPTIONS)
Quando um Front-end moderno (Vue/SPA/Mobile) tenta fazer um POST com JSON, o navegador primeiro manda uma requisição invisível chamada `OPTIONS` (Pre-Flight) para a sua rota, perguntando: *"Aí, posso mandar um POST aqui?"*. Se o seu PHP roteador (`actions.php`) não tiver um "Middleware Controlador de CORS" para cuspir um "Sim, está autorizado", a requisição verdadeira morre no Javascript antes nem de chegar no backend.

### Como o CyanV1 resolve?
Injetamos um Middleware que intercepta todas as requisições `OPTIONS` que chegam no roteador e, via `header()`, empurra para o browser:
- `Access-Control-Allow-Origin: *` ou um site estrito.
- `Access-Control-Allow-Methods: GET, POST, PUT, DELETE`

---

## 2. Roteador SPA (Single Page Application) e DTO

### A Dinâmica do Router (Carregamento Parcial)
No antigo modelo de web, ao mudar de tela, o navegador ficava branco e recarregava toda a árvore de HTML e CSS. O seu Roteador foi projetado como um SPA: nele, o contorno visual (Menu lateral, Cabeçalho) é fixo, e interceptamos os links via `XMLHTTPRequest` (Ajax). Nós mandamos `router.php` cuspir só o "miolo" central num formato de String HTML, e o JavaScript no cliente substitui o pedaço antigo na tela. Performance monstruosa.

### ViewContext (DTO) e Globais
O seu antigo Roteador usava o `global $user;` para dar acesso às variáveis para a tela do meio a ser gerada.
O problema de depender de "Variáveis Globais" é que qualquer arquivo pode poluir ou esmagar o conteúdo do `$user` antes que ele chegue na sua view, causando Bugs dificílimos de rastrear.

Nós introduzimos o padrão **DTO (Data Transfer Object)** através de uma classe chamada `ViewContext`.
Em vez da view "esperar que um objeto caia do céu" no escopo global, o roteador empurra um objeto trancado: `$context->user` ou `$context->url`. Como a classe possui propriedades fortes, se um amador do futuro errar uma chamada e digitar no código `$context->usr`, o Editor de Código dele e o PHP vão gritar o erro antes mesmo dele rodar a página de testes, mantendo a arquitetura impossível de sofrer corrupções parciais por "Typo" (erro gramatical).

---

## 3. Segurança de Arquivos e Múltiplas Barreiras

### O Mito da Extensão (`$info['extension']`)
O PHP base possui a falha mortífera de deixar o desenvolvedor ser enganado por extensões. Imagine que o firewall do builder dizia: *"Só aceite arquivos .jpg"*.
O Hacker renomeia o cavalo de tróia para `script-banco.php.jpg`. Se o Apache do servidor for antigo ou tiver uma configuração frouxa, ele se confunde na hora que alguém entra pelo navegador nessa imagem, o Apache percebe o `.php` intermediário e joga a requisição para a máquina de compilação, e Boom! Seu servidor é infectado pois o "vírus" gerou processamento do lado do servidor tentando ler a foto.

### Mitigação com "Finfo" (Mime-Type Nativo)
Nós isolamos a verificação no seu `storage.php`. Ele ignora completamente o fato da string terminar em `.jpg`. Ele entra fisicamente dentro dos *bytes matemáticos* iniciais do arquivo usando a classe do PHP `finfo_file` para avaliar a Assinatura (MIME TYPE). Se o Mime acusar que o binário é um executável em vez de uma foto, o upload rechaça categoricamente antes de encostar na sua rede.

### Sandboxed File Streamer e CSP
Mas, e se o hacker subir uma foto perfeitinha (MIME correto), mas injetar embutido no XML/comentário da imagem um `<script>rouba-cookie.js</script>` (XSS)? Se nós deixarmos a foto lá publicamente e batermos um link nela direto do web server... a foto abrirá, o navegador lerá o script embutido, e rodará o JS sem piedade no pc do coitado do seu cliente.
Nós resolvemos isso com a **Função Gotejadora Sandbox**.
Os arquivos subidos não terão rota pública do Apache.  Eles moram numa gaveta fechada. Toda requisição pra ler aquele arquivo passará pelo Framework (O Streamer). O Streamer despeja o arquivo para o Cliente mas atrela esse super Header de Segurança junto: `Content-Security-Policy: sandbox; default-src 'none'`.
Quando o header **Sandbox** vem do servidor atrelado numa mídia, o navegador entra num modo recluso: ele proíbe execução de javascript e não carrega janelas em iFrame, bloqueando a foto de fazer qualquer dano.

---

## 4. CSRF via Headers em vez de HTML

### O Que é CSRF?
É quando um intruso forja uma requisição de forma que parece ter vindo de você mesmo. O seu sistema atual exigia criar tags `<input type="hidden" name="csrf">` em formulários de HTML para provar a autenticidade.

### O Problema do Input Oculto e as APIs Modernas
Frameworks REST hoje não mandam Formulários para frente e para trás, eles disparam objetos **JSON** limpos (Via `fetch()`). Manter inputs ocultos quebraria o tráfego da API. Nossa solução moderniza a Defesa: o CSRF será guardado num Header do Navegador (`X-CSRF-TOKEN`). Seus scripts na tela mandarão esse Header passivamente em todas as viagens Ajax sem misturar o JSON do seu banco de dados com lixo estrutural.

---

## 5. HKDF e Criptografia Analítica

### Isolamento HKDF
O arquivo GCM de Segurança (Cipher) do projeto é excelente, mas tinha um defeito fatal: Ele usava *A Misma Chave* (Symmetric Key Root) para criar a String Criptografada Pura, E logo depois ele pegava a Mesma Chave e forjava Hashes na Unha para o banco de dados. Misturar primitivas pode enfraquecer matematicamente algumas assinaturas num cenário exótico e forçado via choques ou rainbow-tables.
Adotamos o **HKDF (HMAC-based Extract-and-Expand Key Derivation)**. Ele passa sua chave principal por um funil de criptografia unidirecional matemático, gerando Chaves Derivadas puras, sendo uma exclusiva para Criptografar GCM, e outra estéril para Hashes. Um colosso de segurança.

### O "Blind Index" (IPs Criptografados no Banco)
Se você criptografa a coluna IP (`REMOTE_ADDR`) numa tabela SQL para agradar à LGPD com GCM, você gera uma string gigante que muda toda hora (O vetor de inicialização muda na criptografia, gerando string diferente mesmo que a senha seja a mesma e o IP fosse o mesmo).
Por não ser nunca mais igual, você não tem como invocar o DB Builder e dizer `WHERE user_ip = '192.1.1'`. Você teria que arrastar 100 mil toras criptografadas para o PHP, descriptografá-las todas, pra então filtrar e exibir na tela!

**A Solução do Blind Index**: Antes de esconder na database com AES, adicionamos uma coluna burra na tabela com um Hash simples e inquebrável (ex: `SHA256(IP + SenhaSistema)`). Se batermos o hash do malfeitor hoje ele apontará 3x o mesmo valor estéril (um número grande que não dá pra saber o IP se roubarem o banco de dados). Mas na barra de pesquisa do seu portal/API, o seu Sistema simula a SHA256 e acha os 3 erros da tabela perfeitamente usando indexação rápida do próprio SQL!

---

Você está em posse de conceitos profundos de Enterprise Software! Qualquer engenheiro que abrir esse diagrama vai considerar essa obra robusta e inquebrável.
