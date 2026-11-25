# sgcore v1.8.0

Versão menor: **play de vídeo só no lightbox**. Sem migration nova.

## Alterado

- **Galerias do site (temas)**: antes, o vídeo aparecia como um player pequeno dentro da grade — clicar nele dava play/pause nativo e o modal nunca abria. Agora o vídeo é um **pôster clicável** (primeira cena + botão ▶): um clique abre o **lightbox** com o vídeo em tela cheia relativa, controles, e navegação ‹ › entre os vídeos (Esc / clique no fundo / × fecham; teclado e leitor de tela respeitados). Páginas de galeria sem vídeo não carregam nada extra — o CSS+JS do lightbox só entra quando existe vídeo na tela.
- **Biblioteca de mídia (painel)**: clicar num vídeo abre o **modal com player** em vez de mandar o arquivo cru para outra aba do navegador. Nome do arquivo embaixo, fechar por ×/Esc/fundo. Imagens seguem abrindo normalmente em nova aba; a tela "Editar" do arquivo continua com o player grande.

## Como fica cada superfície

| Onde | Antes | Agora |
|---|---|---|
| Grade da galeria pública | `<video controls>` mini inline | pôster + ▶ → lightbox |
| Biblioteca de mídia (painel) | link cru em nova aba | lightbox com player |
| Bloco Vídeo no construtor | player inline | **continua** inline (é conteúdo dedicado) |

## Upgrade

Painel → *Configurações → Atualizações* → **Verificar** → **Baixar** → **Instalar agora**. Nenhuma migration — só arquivos e cache. Depois, **Ctrl+F5** no painel e no site. Confirme `CMS_VERSION` no `.env` (`1.8.0`, ou remova a linha para usar o padrão).

## Requisitos
- PHP **≥ 8.4.1** com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.
- MySQL/MariaDB; Node 20+ apenas para recompilar assets (o pacote já vai compilado).

## Changelog completo
Ver `[1.8.0] — 2026-11-25` em `CHANGELOG.md` e o diff:
https://github.com/seerjo0/sgcore/compare/v1.7.1...v1.8.0
