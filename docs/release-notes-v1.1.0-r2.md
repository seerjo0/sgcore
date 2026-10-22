# sgcore v1.1.0-r2

Revisão da **1.1.0**: conserta a tela de Atualizações e pára de derrubar a versão de PHP do domínio a cada atualização. Sem migration nova.

## Corrigido
- **A tela de Atualizações mostrava `:version` em vez da versão**: o texto "Nova versão :version disponível…" era usado como rótulo fixo, sem interpolar a tag. Agora ele aparece como um aviso de largura total — **verde quando há atualização**, com a versão real — e, quando não há, a tela mostra apenas "Você está na última versão do SG Core." O card de versão exibe a versão atual em destaque e a disponível somente quando existir.
- **A versão de PHP voltava para a padrão depois de atualizar**: o pacote traz o próprio `.htaccess` (regras de rewrite e bloqueio de `.env`/`vendor/`), e sobrescrevê-lo apagava o bloco `# php -- BEGIN/END cPanel-generated handler` em que o cPanel/MultiPHP guarda a versão escolhida do domínio. O updater por pacote agora **captura esse bloco** (também aceita uma linha `AddHandler`/`AddType` de PHP em hosts sem cPanel) no `.htaccess` da raiz e em `public/.htaccess` e o recoloca depois de copiar os arquivos, sem duplicar. Atualizações pelo painel/CLI não exigem mais reaplicar a versão no cPanel.

## Atenção
- **A primeira atualização (r1 → r2) ainda roda com o código antigo**: a preservação do handler entra em vigor na r2, ou seja, protege o *próximo* update. Nesse hop, reaplique a versão do PHP no MultiPHP Manager depois de atualizar.
- **Extração manual** (File Manager) continua substituindo o `.htaccess`: confira a versão do domínio no MultiPHP Manager depois de extrair. O caminho pelo painel (`Configurações → Atualizações → Pacote .zip`) preserva o bloco nas atualizações seguintes.

## Upgrade
```bash
git fetch --tags && git checkout v1.1.0-r2
composer install --no-dev --optimize-autoloader   # apenas via Git
php artisan optimize:clear                         # nada novo a migrar
```
- **Hospedagem compartilhada**: baixe `sgcore-1.1.0-r2-public_html.zip` e use **Atualizações → Atualização manual → Pacote .zip** (recomendado — preserva `.env`, `storage/` e o handler de PHP). Se for extrair manualmente, reaplique a versão do PHP no cPanel.

## Requisitos
- PHP **≥ 8.4.1** com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.
- MySQL/MariaDB.

## Changelog completo
Ver seção `[1.1.0-r2] — 2026-10-22` em `CHANGELOG.md` e o diff da tag:
https://github.com/seerjo0/sgcore/compare/v1.1.0-r1...v1.1.0-r2
