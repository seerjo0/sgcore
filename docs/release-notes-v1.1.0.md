# sgcore v1.1.0

Foco em **atualização do CMS**: auto-update agendado (opt-in) e uma página **"Atualizações"** no painel com status, versão e atualização manual. Distribuição Git (`git clone`/tag) + pacote autocontido `sgcore-1.1.0-public_html.zip` para shared hosting.

## Destaques
- **Atualização automática opt-in** (`sgcore:auto-update`): comando agendável que consulta o GitHub e — **só** quando existe versão mais nova — delega ao `sgcore:update --force`, reaproveitando toda a cadeia já existente (modo de manutenção → backup do banco → `git fetch`/`checkout` da tag → `composer install --no-dev` → `migrate --force` → `optimize:clear` → **rollback automático em falha**). O último resultado (from/to/status/when) fica gravado na setting `auto_update_last` para auditoria.
- **Página "Atualizações" na barra lateral** (`Configurações → Atualizações`): card de versão (atual × disponível, link do Release, botão "Verificar"), card com o **status do auto-update** (ligada/desligada, nota de ativação e último resultado) e uma seção **"Atualização manual"** com as três vias já existentes — aplicar via Git (confirmação digitada), baixar o `.zip` da Release ou enviar um `.zip` offline.
- **Zero mudança breaking**: nenhuma migration nova, nenhuma variável de ambiente renomeada, nenhum contrato de módulo alterado. É `MINOR` puramente aditivo.

## Upgrade
```bash
git fetch --tags && git checkout v1.1.0
composer install --no-dev --optimize-autoloader   # apenas via Git
php artisan migrate --force                        # nada novo a migrar
php artisan optimize:clear
```
- **Hospedagem compartilhada**: baixe `sgcore-1.1.0-public_html.zip`, extraia (ou use **Atualizações → Atualização manual → Pacote .zip** no painel) e rode o assistente/migrations normalmente.
- **Auto-update (opcional, duplo opt-in)** — no `.env`:
  ```
  CMS_UPDATER_AUTO=true
  CMS_UPDATER_ALLOW_APPLY=true
  ```
  + o cron padrão `* * * * * php artisan schedule:run`. A entrada do scheduler **só é registrada** com `CMS_UPDATER_AUTO=true` — desligado, nada roda. Flags do comando: `sgcore:auto-update --dry-run` e `--no-backup`.
- **Limitação honesta**: o auto-update precisa de `git` + `composer` no servidor (instalação via Git). Em shared hosting instalado pelo `.zip`, o comando detecta e **recusa** — nesse caso use o caminho manual pelo painel.

## Requisitos
- PHP **≥ 8.4.1** (uma dependência fixou o piso; 8.3 falha no `platform_check` do Composer), com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.
- MySQL/MariaDB. Node 20 só para (re)build de assets.
- O auto-update exige adicionalmente `git` e `composer` disponíveis no `PATH` do usuário do servidor.

## Changelog completo
Ver seção `[1.1.0] — 2026-10-22` em `CHANGELOG.md` e o diff da tag:
https://github.com/seerjo0/sgcore/compare/v1.0.1...v1.1.0
