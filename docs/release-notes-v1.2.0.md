# sgcore v1.2.0

Nova versão menor: o painel passou a **gerenciar** as atualizações, não apenas avisar. Sem migration nova.

## Adicionado
- **Baixar e instalar em etapas** (`Configurações → Atualizações`): *Baixar pacote* guarda o `.zip` da Release em `storage/app/updates` sem tocar no código; o card **Pacote de atualização** mostra versão, tamanho e data, com **Instalar agora** (confirmação por checkbox) e **Descartar**. A instalação entra em manutenção, troca os arquivos, roda as migrations, limpa o cache e apaga o `.zip`. O upload manual de `.zip` continua disponível.
- **Preferências de atualização automática** (salvas no banco, sem editar `.env`): *verificar automaticamente*, *baixar o pacote automaticamente* e *instalar automaticamente* (instalar implica baixar). O agendador roda `sgcore:auto-update` de hora em hora e respeita as opções; a verificação diária das 04:00 também obedece à preferência. A instalação automática usa o pacote (funciona em hospedagem compartilhada) e só cai para o Git em clone limpo sem `.zip` na Release.
- **Preservação do handler de PHP do cPanel** (vinda da 1.1.0-r2): o `.htaccess` do pacote não derruba mais a versão de PHP escolhida no MultiPHP.

## Mudou
- `POST /atualizacoes/baixar` agora **só baixa**; a aplicação virou `POST /atualizacoes/instalar`, com `POST /atualizacoes/config` (preferências) e `POST /atualizacoes/pacote/descartar`. Quem usava o botão "Baixar e atualizar" passa a dar dois cliques: baixar, depois instalar.

## Upgrade
**Pelo painel** (recomendado; exige `CMS_UPDATER_ALLOW_APPLY=true`):
1. Faça backup do banco e do `.env`/`storage/`.
2. `Configurações → Atualizações → Baixar pacote`, depois **Instalar agora**.
3. Se o servidor estiver na 1.1.0-r1/r2, a tela ainda é a antiga: use **Baixar e atualizar** (ou envie o `.zip` em *Atualização manual*).

**Se a página ficar em 503** depois de atualizar, o processo PHP foi interrompido no meio e o modo manutenção ficou preso — apague `storage/framework/down` (Gerenciador de Arquivos) ou rode `php artisan up`. O site volta na hora; confira `storage/logs/laravel.log` para a causa. Esta versão ainda não tem auto-recuperação nem botão "sair da manutenção" (entra na próxima).

**Extração manual** (File Manager): extraia por cima, rode `php artisan migrate --force` + `optimize:clear` se tiver terminal, e reaplique a versão do PHP no MultiPHP (extração manual substitui o `.htaccess`).

## Requisitos
- PHP **≥ 8.4.1** com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.
- MySQL/MariaDB.

## Changelog completo
Ver `[1.2.0] — 2026-10-22` em `CHANGELOG.md` e o diff:
https://github.com/seerjo0/sgcore/compare/v1.1.0-r2...v1.2.0
