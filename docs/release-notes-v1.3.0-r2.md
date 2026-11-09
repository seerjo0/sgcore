# sgcore v1.3.0-r2

Revisão 2 da 1.3.0. **Fundo transparente não vira mais fundo preto no upload de mídia.** Sem migration nova.

## Corrigido

- **PNG/WebP com transparência salvos com fundo preto após o upload.** O `MediaStorage` re-encodeia toda imagem pelo GD, mas o GD só preserva o canal alfa se `imagesavealpha()` estiver ligado — sem a flag, a transparência é achatada sobre o preto padrão do canvas. Isso acontecia em duas trilhas: imagens dentro do limite de dimensão (re-salvas sem nenhuma flag) e o resize, cujo `imagecopyresampled` rodava com *blending* desligado (escurecendo as bordas semitransparentes). Agora as duas trilhas preservam o alfa; o canto 100% transparente e o miolo semitransparente são conferidos por teste novo.

## Conteúdo (recap)

- Tudo da r1 (fix da causa raiz do update por pacote "exit 1"), da 1.3.0 (conversão fiel de tema + "Converter todas as telas" + clique único do updater) e da 1.2.3 (Site Builder: preview sem código-fonte + blocos tipados na conversão).

## Upgrade

Painel → *Configurações → Atualizações* → **Verificar** → **Baixar** → **Instalar agora** (clique único também volta a funcionar se o servidor já estiver na r1/1.3.x; se ainda estiver na 1.2.x, siga o passo "Como sair do travamento agora" das notas da r1: troque o `PackageUpdater.php` ou extraia o zip por cima — entre 1.2.2 e 1.3.x não há migrations).

Confirme `CMS_VERSION` no `.env` (`1.3.0-r2`, ou remova a linha para usar o padrão). Depois, teste reenviar um logo transparente — o arquivo em `storage/app/private/media/…` deve manter o alpha.

## Requisitos
- PHP **≥ 8.4.1** com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.
- MySQL/MariaDB; Node 20+ apenas para recompilar assets (o pacote já vai compilado).

## Changelog completo
Ver `[1.3.0-r2] — 2026-11-09` em `CHANGELOG.md` e o diff:
https://github.com/seerjo0/sgcore/compare/v1.3.0-r1...v1.3.0-r2
