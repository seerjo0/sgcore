# sgcore v1.9.0

Versão menor: **ordem de exibição manual das galerias**. Tem migration (roda automática no instalador do updater).

## Adicionado

- **Setas ↑/↓ em Mídia → Galerias**: cada galeria ganhou a coluna "Ordem". Um clique sobe/descende a galeria uma posição — e vale na hora para tudo que lista galerias: a própria tela, o seletor de galeria dos slots de tema (*Aparência → Editar conteúdo*) e módulos que consomem a biblioteca (ex.: *Galerias Alliance*, incluindo o JSON `galerias/ultimas.json` que alimenta a home).
- **Comportamento preservado**: na atualização, a ordem pública que você já tinha (mais recentes primeiro) é semeada como base — nada muda sozinho. Galeria nova continua entrando no topo; é só usar as setas para reorganizar.
- A ordem interna das fotos/vídeos *dentro* de cada galeria (tela de editar) permanece com as setas próprias de cada item, independentes desta.

## Migration

`galleries.sort_order` (inteiro, default 0) + seeding da ordem atual. O updater aplica sozinho (`migrate --force`) — nada a fazer manualmente.

## Upgrade

Painel → *Configurações → Atualizações* → **Verificar** → **Baixar** → **Instalar agora** → **Ctrl+F5**. Depois: Mídia → Galerias, arrume a ordem com as setas e confira na home do site. Confirme `CMS_VERSION` no `.env` (`1.9.0`, ou remova a linha para usar o padrão).

## Requisitos
- PHP **≥ 8.4.1** com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.
- MySQL/MariaDB; Node 20+ apenas para recompilar assets (o pacote já vai compilado).

## Changelog completo
Ver `[1.9.0] — 2026-11-25` em `CHANGELOG.md` e o diff:
https://github.com/seerjo0/sgcore/compare/v1.8.0...v1.9.0
