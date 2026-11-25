# sgcore v1.6.0

Versão menor: **suporte a vídeo na biblioteca de mídia e nas galerias**. Sem migration nova.

## Adicionado

- **Envie vídeos pelo painel** (Biblioteca de mídia): MP4, WebM, MOV e OGV, vários por vez. O arquivo é guardado **sem recodificar** — nada de perda de qualidade (não passa por GD/ffmpeg). Limite dedicado de **100 MB** por vídeo (`CMS_MEDIA_MAX_VIDEO_BYTES` no `.env` ajusta; imagens seguem limitadas a 10 MB por `CMS_MEDIA_MAX_UPLOAD_BYTES`).
- **Vídeos visíveis e tocáveis em todo lugar**: a biblioteca, a tela do arquivo, as galerias e o seletor de mídia (inclusive dentro do construtor de páginas) mostram o player com prévia da primeira cena e selo "Vídeo".
- **Seek no site**: os vídeos são servidos com suporte a *range requests* (HTTP 206), então o visitante pode avançar/voltar na barra de progresso mesmo em arquivos grandes.
- **Galerias com vídeo**: itens de vídeo numa galeria aparecem como `<video controls>` na página pública, com legenda quando configurada.
- **Slots de imagem à prova de vídeo**: se alguém tentar usar um vídeo num slot `image`/`logo` do tema, o painel agora avisa ("Selecione uma imagem válida da biblioteca.") em vez de publicar uma imagem quebrada.

## Upgrade

Painel → *Configurações → Atualizações* → **Verificar** → **Baixar** → **Instalar agora**. Nenhuma migration — só arquivos e cache.

Após atualizar: **Ctrl+F5 no painel** (bundle do editor re-gerado), então *Configurações → Atualizações* deve mostrar **1.6.0**. Envie um vídeo na Biblioteca de mídia e abra-o em uma galeria pública para conferir o seek. Confirme `CMS_VERSION` no `.env` (`1.6.0`, ou remova a linha para usar o padrão).

> Nota para hospedagens: uploads grandes exigem `upload_max_filesize`/`post_max_size` ≥ o limite desejado no PHP do painel (cPanel → *Select PHP Version → Options*). O limite do CMS só pega até onde o PHP deixar.

## Requisitos
- PHP **≥ 8.4.1** com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.
- MySQL/MariaDB; Node 20+ apenas para recompilar assets (o pacote já vai compilado).

## Changelog completo
Ver `[1.6.0] — 2026-11-25` em `CHANGELOG.md` e o diff:
https://github.com/seerjo0/sgcore/compare/v1.5.1...v1.6.0
