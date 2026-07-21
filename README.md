# Operacional Lokas MC

Sistema web para controle dos processos da pasta operacional da Lokas MC. O escopo de domínio permanece voltado a usuários, eventos e frequência, mas esta etapa implementa somente a base de acesso: não há tabelas de eventos ou presenças.

O cadastro público é desativado. Contas são provisionadas por um administrador com o comando interativo `user:create`.

## Tecnologias

- Laravel 13 e PHP 8.5;
- Vue 3, Inertia 3, TypeScript e Vite 8;
- Node.js 24 e npm;
- MariaDB 11.8 LTS;
- Mailpit para captura local de e-mails.

## Ambiente local no macOS com Homebrew

Instale as ferramentas (o Composer pode ser instalado pelo Homebrew ou pelo instalador oficial):

```bash
brew install php@8.5 composer node@24 mariadb@11.8 mailpit
brew services start mariadb@11.8
```

Garanta que PHP 8.5 e Node 24 estejam no `PATH`, conforme as instruções exibidas pelo Homebrew. O Mailpit não precisa estar ativo para testes automatizados, pois eles usam o mailer em memória.

### Banco local

Crie um banco e um usuário exclusivos para desenvolvimento. As credenciais abaixo são deliberadamente não sensíveis e correspondem ao `.env.example`:

```bash
mariadb -u root
```

```sql
CREATE DATABASE operacional_lokasmc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'operacional_local'@'localhost' IDENTIFIED BY 'operacional_local';
GRANT ALL PRIVILEGES ON operacional_lokasmc.* TO 'operacional_local'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Instalação

```bash
composer install
npm ci
cp .env.example .env # somente se .env ainda não existir
php artisan key:generate
php artisan migrate
npm run build
```

O `.env.example` usa MariaDB em `127.0.0.1:3307`, SMTP do Mailpit em `127.0.0.1:1025` e interface web do Mailpit em `http://127.0.0.1:8025`.

### Migrações

```bash
php artisan migrate
php artisan migrate:rollback
php artisan migrate
# recria todas as tabelas; apaga os dados locais
php artisan migrate:fresh
```

Não há migrações de eventos ou frequência nesta etapa.

## Executar a aplicação

Em terminais separados:

```bash
brew services start mariadb@11.8
mailpit
php artisan serve
npm run dev
```

A aplicação fica em `http://127.0.0.1:8000`; o Vite informa sua própria URL no terminal; o Mailpit fica em `http://127.0.0.1:8025`. Encerre o Mailpit iniciado diretamente com `Ctrl+C`.

Como alternativa para PHP, Vite, fila e logs em um único comando, use `composer dev`.

## Usuários e autenticação

Não existem rotas GET ou POST `/register`. Crie uma conta administrativamente:

```bash
php artisan user:create
```

O comando solicita nome, e-mail, senha e confirmação de forma interativa; a senha e a confirmação não são exibidas. Não existe senha padrão ou credencial fixa no código.

O login regenera a sessão, limita tentativas por combinação de e-mail e IP e usa uma resposta genérica para credenciais inválidas. A recuperação de senha também responde genericamente para não revelar contas existentes. Em desenvolvimento, inicie o Mailpit para abrir os links enviados.

## Testes e qualidade

Backend:

```bash
php artisan test
composer lint:check
composer types:check
# Pint, PHPStan e testes
composer test
```

Frontend:

```bash
npm run lint:check
npm run format:check
npm run typecheck
npm run build
# todos os comandos acima
npm run check
```

Os testes automatizados usam SQLite em memória e cobrem login válido e inválido, mensagem genérica, regeneração de sessão, limitação de tentativas, redirecionamento de visitantes, painel protegido, logout, recuperação e redefinição de senha, ausência das duas rotas de cadastro e criação administrativa de usuário.

## Variáveis e segurança

`.env.example` contém apenas valores locais demonstrativos. Nunca versione `.env`, chaves, credenciais, tokens ou dumps. Para produção, configure no mínimo:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://operacional.exemplo.com
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

Use credenciais exclusivas com privilégio mínimo, gere `APP_KEY` uma única vez e defina um `PASSKEYS_USER_HANDLE_SECRET` aleatório e independente. Não execute `key:generate` novamente em uma instalação existente. Defina `SESSION_DOMAIN` apenas se precisar compartilhar cookies entre subdomínios. `SameSite=none` exige HTTPS e só deve ser usado quando necessário. `APP_URL` deve ser exatamente a origem pública usada por passkeys/WebAuthn.

## Produção tradicional com PHP-FPM

Use Linux suportado, PHP 8.5 FPM com as extensões exigidas pelo Laravel (`bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `session`, `tokenizer`, `xml` e `zip`), Composer 2, Node.js 24 para a etapa de build e MariaDB 11.8. O Mailpit é somente para desenvolvimento; em produção configure um provedor SMTP real.

Exemplo de preparação de uma versão:

```bash
composer install --no-dev --classmap-authoritative
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

O usuário do PHP-FPM precisa escrever somente em `storage/` e `bootstrap/cache/`. Mantenha o restante do código somente leitura, preserve o `.env` fora do controle de versão, faça backup do banco antes das migrações e restrinja o MariaDB à rede necessária.

### Nginx

Configure `root /caminho/operacional-lokasmc/public;`, encaminhe somente requisições PHP do front controller para o socket do PHP-FPM e use `try_files $uri $uri/ /index.php?$query_string`. Bloqueie arquivos ocultos e nunca exponha a raiz do repositório, `.env`, `storage/` ou `vendor/`. Habilite TLS, HSTS depois de validar HTTPS e cabeçalhos de segurança adequados.

### Apache

Defina o `DocumentRoot` como `/caminho/operacional-lokasmc/public`, habilite `mod_rewrite` e PHP-FPM via `proxy_fcgi`, permita os overrides necessários ao `public/.htaccess` e negue acesso à raiz do projeto e a arquivos ocultos. Não use o servidor embutido do Artisan em produção.

### Processos recorrentes

Se houver tarefas agendadas:

```cron
* * * * * cd /caminho/operacional-lokasmc && php artisan schedule:run >> /dev/null 2>&1
```

Use systemd, Supervisor ou equivalente para `php artisan queue:work` quando filas assíncronas forem habilitadas. Após cada implantação, reinicie PHP-FPM e workers de maneira controlada.
