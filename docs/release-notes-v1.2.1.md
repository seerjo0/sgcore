# sgcore v1.2.1

Correções no gerenciador de atualizações. Sem migration nova.

## Corrigido

- **O site ficava em 503 depois de atualizar.** O `artisan up` era chamado com `--secret`, e o comando `up` não aceita essa opção: o artisan saía com erro, `storage/framework/down` ficava no disco e o site continuava em manutenção. Acontecia nos dois caminhos — pacote (`PackageApply`: painel e `sgcore:auto-update`) e Git (`UpdateRunner`: `sgcore:update`). Agora o `up` roda sem opções, sempre (inclusive quando a atualização falha); se o artisan não subir, os arquivos de manutenção são removidos à mão.
- **"Verificar" não enxergava a Release nova.** A ação lia o cache de 6 h, então uma versão publicada depois da última consulta ficava invisível e o painel dizia "já está na última". Agora o botão força a consulta ao GitHub. Além disso, falha de rede/HTTP não é mais cacheada — um 429 do GitHub bloqueava a verificação por horas.
- **429 cru na tela.** Os limites de tentativas devolvem mensagem ("Muitas tentativas, aguarde X s") em vez da página de erro do Laravel. Baixar: 3 por 5 min; instalar/aplicar: 2 por 5 min; verificar: 5 por minuto.
- **Manutenção presa sem FTP.** A página de Atualizações continua acessível durante a manutenção, libera sozinha um `down` sem atualização em andamento (sem lock) e tem o botão **Sair da manutenção**. A cópia termina mesmo se o navegador sair (`ignore_user_abort` + `set_time_limit(0)`), um `register_shutdown_function` tenta religar o site em erro fatal e `storage/framework/update.lock` impede duas atualizações ao mesmo tempo.

## Upgrade

**O salto 1.2.0 → 1.2.1 ainda usa o updater da 1.2.0**, que tem o bug do `--secret`. Duas opções:

1. **Manual (recomendado):** extraia `sgcore-1.2.1-public_html.zip` por cima pelo File Manager, rode `php artisan optimize:clear` (se tiver terminal) e reaplique a versão do PHP no MultiPHP — extração manual sobrescreve o `.htaccess`.
2. **Pelo painel:** faça backup, clique em *Baixar* e *Instalar agora*. A atualização é aplicada; se a página voltar 503, apague `storage/framework/down` (ou rode `php artisan up`). Da 1.2.1 em diante esse passo não é mais necessário.

Confirme `CMS_VERSION` no `.env` (deve ser `1.2.1`, ou remova a linha para usar o padrão).

## Requisitos
- PHP **≥ 8.4.1** com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.

## Changelog completo
Ver `[1.2.1] — 2026-10-22` em `CHANGELOG.md` e o diff:
https://github.com/seerjo0/sgcore/compare/v1.2.0...v1.2.1
