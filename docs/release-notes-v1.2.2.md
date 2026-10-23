# sgcore v1.2.2

Atualização acompanhada de ponta a ponta: progresso em tempo real no painel, página de manutenção própria e `.htaccess` que preserva o handler PHP do cPanel. Sem migration nova.

## Adicionado

- **Tela de progresso da atualização.** Em *Configurações → Atualizações*, os botões de instalar e de atualizar levam para um terminal no tema do painel, com barra, percentual e log com carimbo de hora. A resposta é transmitida em NDJSON (`StreamedResponse`), com etapas de download, verificação, extração, cópia (percentual a cada ponto ou 1 s), merge do `.htaccess`, migrations e caches. Se a aba fechar, a atualização continua no servidor; ao terminar, a página redireciona sozinha para o painel.
- **Página de manutenção temática** (`503`): recado em português, tempo estimado e links para tentar de novo e para o painel. É a view usada pelo `artisan down --render=errors::503`.
- **Progresso no download**: o pacote informa bytes recebidos e total enquanto baixa.

## Corrigido

- **A manutenção não era ativada.** O `artisan down --render=errors::503` falhava com código 1 porque a view `errors::503` não existia — e a falha passava batido, então o site ficava no ar durante a cópia. A view existe agora e a aplicação confere o código de saída e a criação de `storage/framework/down`, abortando com mensagem clara se não der certo.
- **Atualização por File Manager apagava o PHP do cPanel.** Extrair o pacote sobrescrevia o `.htaccess` e o site voltava ao PHP padrão. O `.htaccess` da raiz passa a ser gerado entre `# BEGIN sgcore` e `# END sgcore`, e o updater substitui **somente esse bloco** — o `# php -- BEGIN cPanel-generated handler` e qualquer regra sua continuam intactos. Um guardião guarda o handler em `storage/framework/php-handlers.json` e o reaproveita se o arquivo for sobrescrito; a página de Atualizações também repara sozinha ao abrir.
- **`.htaccess` legado (sem marcadores).** Na primeira atualização, o merge remove apenas as regras antigas do sgcore e re-anexa o handler, sem duplicar `RewriteRule`.

## Upgrade

O salto **1.2.1 → 1.2.2** já roda o updater corrigido da 1.2.1 e pode ser feito pelo painel, agora com progresso.

1. Faça backup dos arquivos e do banco.
2. Em *Configurações → Atualizações*, clique em **Baixar** e depois em **Atualizar com progresso**.
3. Ao terminar, confirme no File Manager que o bloco `# php -- BEGIN cPanel-generated handler` continua no `.htaccess` da raiz e que a versão em *Configurações* mostra `1.2.2`.

Se você atualizar por extração manual, o guardião do handler entra em ação na próxima abertura da página de Atualizações.

Confirme `CMS_VERSION` no `.env` (deve ser `1.2.2`, ou remova a linha para usar o padrão).

## Requisitos
- PHP **≥ 8.4.1** com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.

## Changelog completo
Ver `[1.2.2] — 2026-10-23` em `CHANGELOG.md` e o diff:
https://github.com/seerjo0/sgcore/compare/v1.2.1...v1.2.2
