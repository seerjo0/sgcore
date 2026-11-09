# sgcore v1.3.0

O construtor agora **enxerga o tema**: páginas convertadas de um tema ficam com o visual do próprio tema (CSS + assets) no Pré-visualizar e no site público, e um clique só converte **todas as telas** do tema em páginas editáveis. Inclui todas as correções da 1.2.3 e da revisão r1 (clique único do updater). Sem migration nova.

## Adicionado

- **Conversão fiel de tema (estilos carregados).** Ao usar "Converter e editar" no menu *Aparência*, o `styles.css` do tema passa a ser gravado como CSS da página convertada — o Pré-visualizar do construtor e o site público exibem a página **com a cara do tema** (hero, cores, tipografia), não um bloco sem estilo. Referências relativas do CSS (`url(...)`) e de imagens (`src`/`poster`) são reescritas para `/theme-assets/{tema}/...`, então temas com imagens/fontes próprias (inclusive importados por ZIP) continuam funcionando.
- **"Converter todas as telas".** Novo botão no card de cada tema: cria de uma vez uma página **rascunho por template** (home, page, 404). Elas aparecem no menu **Páginas** e cada uma abre no **construtor** com os blocos já tipados e editáveis (painel de Propriedades funcionando). A página home ativa do site não é tocada — tudo entra como rascunho.

## Corrigido

- **Clique único "Atualizar com progresso"** (trazido da r1): a tela de progresso devolvia ao stream o modo interno `update`, que o controller confundia com o modo instalar (exigia pacote já baixado) e a atualização morria com *"Nenhum pacote baixado. Rollback foi tentado."*. O botão de um clique funciona de novo; o caminho Baixar → Instalar agora sempre funcionou e continua.
- **Pré-visualizar/exibição como "código HTML puro"** (trazido da 1.2.3): os componentes de layout escapavam os filhos já renderizados; páginas com blocos aninhados (qualquer conversão de tema) saíam como código-fonte literal. Além disso, a conversão parava de vazar o `<head>` do tema como blocos de lixo e passou a tipar os nós (`pb-*`), devolvendo o painel de Propriedades aos blocos importados.

## Upgrade

Em *Configurações → Atualizações*: **Verificar** → **Baixar** → **Instalar agora** (ou, a partir da r1/1.3.0 já instalada, o clique único "Atualizar com progresso"). Nenhuma migration — só cache.

- **Página já convertada antes desta versão?** Reconverta o tema (ou "Converter todas as telas") para ganhar o CSS do tema; páginas antigas continuam funcionando, apenas sem o visual do tema no preview.
- Confirme `CMS_VERSION` no `.env` (deve ser `1.3.0`, ou remova a linha para usar o padrão).

## Requisitos
- PHP **≥ 8.4.1** com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.
- MySQL/MariaDB; Node 20+ apenas para recompilar assets (o pacote já vai compilado).

## Changelog completo
Ver `[1.3.0] — 2026-11-09` em `CHANGELOG.md` e o diff:
https://github.com/seerjo0/sgcore/compare/v1.2.3...v1.3.0
