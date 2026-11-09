# sgcore v1.3.0-r1

Revisão 1 da 1.3.0. **A causa raiz do "As migrations falharam (exit 1)"** que derrubava toda atualização feita pelo painel — consertada. Sem migration nova, sem mudança no construtor além da 1.3.0.

## O problema

Desde a **1.2.0**, qualquer atualização por pacote pelo painel (Baixar → Instalar, clique único, upload manual do .zip, auto-update) morria no passo de migrations com:

> Update failed: As migrations falharam após copiar os arquivos (exit 1). Rollback was attempted.

O que acontecia: o runner interno chama `Artisan::call('migrate', ['force' => true])` — **sem os dois traços**. O ArrayInput do Symfony interpreta chave sem `--` como **argumento**, e o comando `migrate` não tem argumento `force`: ele lança *"The force argument does not exist"*. O `catch` virava isso em "exit 1" sem mostrar a causa, e o rollback restaurava os arquivos — o site ficava vivo na versão antiga e ninguém conseguia descobrir por que falhou. Os testes nunca pegaram porque todos injetam um runner falso que ignora os argumentos.

## A correção

- Opções agora chegam ao Artisan com seus `--` (`['--force' => true]`).
- Quando um comando interno ainda falhar, o painel mostra **`Causa: …`** com o output real do artisan (ou a exception capturada) no terminal de progresso, e o rollback continua protegendo o site.
- Teste novo (`UpdaterDiagnosticsTest`) cobre o runner real e a propagação da causa.
- Validado em réplica: site 1.2.2 aplicando o pacote da 1.3.0 reproduzia o erro exato do log do usuário; com o fix, o passo volta `Nothing to migrate` (exit 0).

## Como sair do travamento agora (escolha uma)

Importante: o passo de migrate roda com o **código antigo** (o processo PHP da atualização não recarrega as classes no meio), então nenhum pacote novo resolve sozinho pelo painel enquanto o servidor tiver a 1.2.x. Entre 1.2.2 e 1.3.x **não há migrations pendentes** — a troca é só de arquivos:

1. **Um arquivo** (recomendado): via File Manager, sobrescreva no servidor
   `app/Modules/Core/Services/PackageUpdater.php` pela versão deste release
   (está dentro do zip). Depois: *Configurações → Atualizações → Baixar → Instalar agora* funciona normal.
2. **Extração manual**: extraia `sgcore-1.3.0-r1-public_html.zip` por cima do site (o zip não contém `.env`; `storage/` é preservado). Abra a página de Atualizações em seguida — o guardião repara o handler de PHP do cPanel no `.htaccess` automaticamente. Confira a versão em *Configurações* (deve ser `1.3.0-r1`).
3. Se preferir terminal (SSH): com a extração manual, `php artisan optimize:clear` basta; não há `migrate` a rodar.

## Conteúdo (recap da 1.3.0)

- **Conversão fiel de tema**: o `styles.css` do tema acompanha a página convertada (preview e site público com o visual do tema; assets relativos via `/theme-assets/{tema}/`).
- **"Converter todas as telas"**: todas as telas do tema viram páginas no menu **Páginas**, cada uma com seu projeto no **construtor**.
- **Pré-visualizar sem "código HTML puro"** + blocos convertados tipados com Propriedades + clique único do updater (fix r1 da 1.2.3).

## Requisitos
- PHP **≥ 8.4.1** com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.
- MySQL/MariaDB; Node 20+ apenas para recompilar assets (o pacote já vai compilado).

## Changelog completo
Ver `[1.3.0-r1] — 2026-11-09` em `CHANGELOG.md` e o diff:
https://github.com/seerjo0/sgcore/compare/v1.3.0...v1.3.0-r1
