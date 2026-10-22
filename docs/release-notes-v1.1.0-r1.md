# sgcore v1.1.0-r1

Revisão da **1.1.0**: corrige um bug de navegação no painel. Sem migration nova, sem mudança de schema ou de contrato — trocar pelos arquivos/`vendor` da versão anterior é suficiente.

## Corrigido
- **Barra lateral destacava "Configurações" e "Atualizações" ao mesmo tempo** ao abrir a página de Atualizações. O link de Configurações dependia de `request()->routeIs('admin.configuracoes.*', '!admin.configuracoes.atualizacoes.*')`, mas `routeIs()` não interpreta `!` como negação: o padrão negado nunca casava e `admin.configuracoes.*` continuava ativo na tela de Atualizações. A condição agora é explícita (`routeIs('admin.configuracoes.*') && ! routeIs('admin.configuracoes.atualizacoes.*')`) e há teste de regressão verificando a classe ativa de cada link da barra lateral.

## Alterado
- **Verificador de versões agora entende o sufixo de revisão** (`-rN`). O `version_compare()` do PHP classifica `1.1.0-r1` como *anterior* a `1.1.0` (qualificador desconhecido = pré-release), então a revisão jamais apareceria como atualização disponível para quem já está na 1.1.0. O novo `App\Modules\Core\Support\Version` (usado pelo verificador, `sgcore:update` e `sgcore:auto-update`) ordena `1.1.0 < 1.1.0-r1 < 1.1.0-r2 < 1.1.1`.

## Upgrade
```bash
git fetch --tags && git checkout v1.1.0-r1
composer install --no-dev --optimize-autoloader   # apenas via Git
php artisan optimize:clear                         # nada novo a migrar
```
- **Hospedagem compartilhada**: baixe `sgcore-1.1.0-r1-public_html.zip` e extraia por cima da instalação (ou use **Atualizações → Atualização manual → Pacote .zip**). O `.env` não vai no pacote; `storage/` é preservado.
- **Já na 1.1.0**: o painel passa a oferecer a r1 normalmente; como não há migration, basta aplicar o pacote e limpar o cache.

## Requisitos
- PHP **≥ 8.4.1** com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.
- MySQL/MariaDB.

## Changelog completo
Ver seção `[1.1.0-r1] — 2026-10-22` em `CHANGELOG.md` e o diff da tag:
https://github.com/seerjo0/sgcore/compare/v1.1.0...v1.1.0-r1
