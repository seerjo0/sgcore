# Changelog

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/), versionado por [SemVer](https://semver.org/lang/pt-BR/).

## [Unreleased]

### Adicionado
- **Atualização automática opt-in** (`sgcore:auto-update`): comando agendável que consulta o GitHub, e — só quando há versão mais nova — delega ao `sgcore:update --force` (mesma cadeia segura: manutenção, backup do banco, `composer install --no-dev`, `migrate --force`, `optimize:clear` e rollback em falha). Exige opt-in duplo `CMS_UPDATER_AUTO=true` + `CMS_UPDATER_ALLOW_APPLY=true` e só faz sentido em instalação via Git (rejeita quando não é `git checkout` ou quando há bloqueios). Entrada no scheduler é registrada **só** com `CMS_UPDATER_AUTO=true` (horário, `withoutOverlapping`); último resultado gravado na setting `auto_update_last`. Flags do comando: `--dry-run`, `--no-backup`. 4 testes.
- **Página "Atualizações" no painel (barra lateral)**: `admin.configuracoes.atualizacoes.index` (rota GET, gate `manage-settings`) com card de versão (atual × disponível + link do Release + "Verificar"), card do **status do auto-update** (ligada/desligada, nota e o último resultado `auto_update_last`) e uma seção **"Atualização manual"** com as ações já existentes reaproveitadas — aplicar via Git (confirmação digitada), baixar o `.zip` da Release e enviar um `.zip` offline. Novo link `nav.updates` na sidebar. 3 testes.

### Planned
- Comentários nos posts do blog.
- Tags de post (além de categoria única).
- Feed RSS/Atom do blog.
- API headless (`/api/v1/posts`, `/api/v1/pages`).
- Multi-idioma de conteúdo por registro.

## [1.0.1] — 2026-10-21

### Adicionado
- **Atualização por pacote `.zip` (estilo WordPress)** para shared hosting, sem `git`/`composer`/`node` no servidor:
  - O botão "Aplicar atualização" ganhou seletor de método **Git / Pacote .zip** (upload do `.zip` ou download do asset da Release).
  - `sgcore:package` gera o `.zip` autocontido a partir do HEAD (vendor + assets de build + front-controller raiz `index.php` + regras `.htaccess`/`web.config` endurecidas + manifesto `sgcore-package.json`), pronto para anexar à Release. Flags: `--pkg-version`, `--out`, `--skip-composer`, `--skip-npm`, `--php-min`.
  - Download do asset `.zip` com validação de host (só `github.com`/`*.githubusercontent.com`), teto de tamanho (`CMS_UPDATER_MAX_PACKAGE_BYTES`) e verificação de digest **sha256** quando a Release o fornece.
  - Aplicação in-place preservando `.env`, `storage/` e `bootstrap/cache/` (lista `cms.packager.protected`), sob modo de manutenção, com `migrate --force` e `optimize:clear`.
  - continua opt-in atrás de `CMS_UPDATER_ALLOW_APPLY=true`; sub-flags `CMS_UPDATER_ALLOW_PACKAGE`/`CMS_UPDATER_ALLOW_DOWNLOAD` (default `true` quando apply está ligado) e `CMS_UPDATER_DOWNLOAD_TIMEOUT`.
  - Serviços `PackageBuilder`/`PackageSource`/`PackageUpdater` + comando `sgcore:package`; suíte sobe para 315 testes, Pint limpo.
- **Biblioteca de blocos (Construtor)**: nova categoria **Blocos** com 10 presets compostos apenas por componentes já allowlisted — Hero (imagem+texto), Hero centralizado, Recursos, CTA, Depoimento, Preços, Galeria, Estatísticas, Equipe e Rodapé (usando os tokens `{site:name}`/`{year}` resolvidos no render).
- **Componente HTML custom (Embed)**: novo tipo `html`; o markup digitado passa pelo `BuilderSanitizer` no publicar **e** no render (sem `script`/`iframe`/`on*`/`javascript:`/`data:`). Prop salvo em `data-pb-code` (não `content`, para o extrator não sobrescrever).
- **CSS por página**: coluna `pages.builder_custom_css` (migration reversível), modal de edição no construtor, endpoint `admin.paginas.builder.css` (`can:manage-pages`); o `Renderer` sanitiza e injeta o CSS da página **por último** (ganha sobre tokens), com invalidação de cache da versão e reflexo imediato no preview.
- **Fluxo de publicação**: botões **Pré-visualizar** (renderiza o rascunho pelo mesmo pipeline do publicar em um iframe em tela cheia, sem persistir), **Ver publicado** (link à URL pública `/` ou `/slug`) e **Descartar** (rascunho volta à última revisão publicada, ou é limpo se nunca publicado).
- **Navegador de Camadas**: modal nativo com a árvore de componentes — seleção por clique e reordenação subir/descer via `component.move(parent, {at})`; a árvore se atualiza ao selecionar/editar.
- **Ferramentas de estilo**: **Copiar / Colar / Limpar estilo** na barra superior, usando a API pública `getStyle()`/`setStyle()`.
- **Navegador de mídia nativo** do construtor — substitui o seletor compartilhado em Tailwind (que, na página standalone do editor, ficava sem estilo e fora da tela): busca por `admin.midia.buscar` e **upload direto** por `admin.midia.store`, com grade e seleção que alimentam o contrato `pb-media-preview`/`pbApplyMediaSelection`. Reusa as traduções `media.*`.
- **Editor mais legível**: cabeçalhos de categoria/segmento com alto contraste (chip claro + texto escuro), **ícones SVG próprios por bloco** e botões da barra superior com ícones (salvar, publicar, histórico, ferramentas).
- **Manual de Desenvolvimento — Módulos & Temas** (PDF em `D:\Projetos\manual_sgcore`): arquitetura, convenções, boas práticas e **modelos de prompt para desenvolvimento com IA**; serve de guia e de contexto anexável a agentes.
- **Instalador pronto para shared hosting** (`sgcore-1.0.0-public_html.zip`, ~8,5 MB, 6.783 arquivos): pacote autocontido gerado do HEAD com `vendor/` de produção + assets + front-controller raiz + `.htaccess`/`web.config`, sem `.env`/dev, acompanhado de `COMO-INSTALAR.txt`.
- **Importar página do tema para o construtor**: novo `App\Modules\Themes\Services\ThemePageImporter` lê o HTML estático de um template do tema (`index.html`/`page.html`/`404.html`), descarta o chrome (header/footer/`<nav>` menus) e converte o `<main>` numa árvore de **componentes nativos do builder** (section/container/row/col/heading/text/image/button/list/blockquote), mapeando `data-cms-slot` dinâmico para os bindings do construtor (`site.name`→`{site:name}`, `page.title`→`{page:title}`) e o que não casa para um bloco `html` sanitizado. Gera um `builder_draft` editável e cria uma `Page` em rascunho. Botão **"Converter e editar"** em Aparência por tema válido (rota `admin.aparencia.importar`, exige `manage-appearance` + `manage-pages`), abrindo direto no construtor. Chaves `themes.import_*`/`imported_page` nos dois idiomas.
- **Páginas de erro personalizadas**: `App\Support\ErrorPresenter` + view autocontida `resources/views/errors/error.blade.php` (CSS embutido, sem depender de Vite) substituem as telas padrão do Laravel/Ignition para 400/401/403/404/405/413/419/429/500/503/504 e genéricas, com marca sgcore e CTAs. Em `APP_DEBUG=true` exibem os mesmos detalhes técnicos do Laravel (classe da exceção, mensagem, `arquivo:linha`, trecho do código com a linha do erro destacada, stack resumida e dados da requisição/cabeçalhos). `ValidationException` e `AuthenticationException` continuam com o redirect/JSON padrão do framework.
- **Suite de testes**: sobe para **337 testes / 1412 asserções** (novos: tela de erro, importação tema→construtor, preview/descartar/CSS por página, HTML sanitizado, upload de mídia JSON e formato por-páginas), Pint limpo; `pagebuilder.*`, `errors.*` e `themes.*` alinhados alfabeticamente em `en`/`pt_BR`.

### Corrigido
- **Salvamento/Publicação do Construtor (HTTP 422 em todo autosave/publicar)**: o GrapesJS ≥ 0.21 (page manager no core) serializa os componentes sob `pages[].frames[].component`, mas o backend exigia uma chave `components` no topo e devolvia 422 "Projeto do construtor inválido". `ProjectExtractor::roots()/isProject()` aceitam agora ambos os formatos (flat e por-páginas), o `PageBuilderController` valida por `ComponentRegistry::isProject()`, e o editor recarrega o rascunho quando há `pages`. O limite `max_json_bytes` foi ajustado a `8_000_000` (margem segura sob `post_max_size` de 8M).
- **Avisos do editor agora revelam a causa real**: novo `httpError()` exibe o status HTTP + mensagem do servidor (com caso dedicado de sessão expirada para 401/419 e aviso de erro de rede indicando o endpoint); os endpoints de autosave/publicar/preview/etc. são normalizados para **mesma origem** (evita falha cross-origin quando o painel é aberto por host/porta diferente de `APP_URL`).
- **`sgcore:package` não executava**: a opção `--version` do comando colidia com a opção global `--version` do console (`An option named "version" already exists`), travando qualquer invocação; renomeada para `--pkg-version` (os testes só cobriam `PackageBuilder::assemble`, nunca o comando).
- **Mídia dentro do editor**: o seletor de imagem parou de depender do `media::picker` em Tailwind (incompatível com a página standalone do construtor).

## [1.0.0] — 2026-10-13

Primeira versão estável (v1) do sgcore: CMS modular e self-hosted, construído com Laravel 13 e PHP ≥ 8.3. Distribuído via repositório Git (`git clone`) + assistentes de instalação web (`/instalar`) e CLI (`php artisan cms:install`).

### Adicionado
- **Núcleo modular**: `app/Modules/{Core,Installer,Auth,Media,Pages,PageBuilder,Themes,Blog}` com provider, migrations, rotas e views auto-descobertos. `core`/`installer`/`auth` são protegidos; todos os bundled são imutáveis (§8.13).
- **Instalador web** (`/instalar`) com verificação de requisitos, conexão de banco, dados do site/admin, lock em `storage/app/installed.lock`.
- **Instalador CLI** `php artisan cms:install` (equivalente, para automação). Flags `--sample`, `--lang`, e agora `--dev` (mantém `APP_ENV=local`/`APP_DEBUG=true`); default é produção.
- **Autenticação** com sessão, throttle, intended-redirect, contas inativas; middlewares `role` e `active`.
- **Usuários e permissões** (admin|editor) com gates por área (`manage-pages`, `manage-media`, `manage-menus`, `manage-blog`, `manage-users`, `manage-appearance`, `manage-modules`, `manage-settings`).
- **Biblioteca de mídia** com upload GD, redimensionamento, servir via `GET /media/{path}` (guard de traversal), galerias com legenda/ordenação, seletor de mídia reutilizável (modal).
- **Páginas e menus**: CRUD com slug único, estados rascunho/publicado, conteúdo rich-text sanitizado por allowlist, home única, menus aninhados (2 níveis), páginas reservadas (`admin`, `instalar`, `media`, `storage`, `theme-assets`, `blog`, `up`).
- **Engine de temas**: `theme.json` com templates, slots (`text`, `richtext`, `image`, `logo`, `gallery`) escopo global/página, menus via `data-cms-menu`, 3 temas embutidos (classic, modern, gallery), import de ZIP com proteção Zip-Slip, ativação por setting, fallback `Route::fallback` para `/{slug}` e 404 temático.
- **Configurações do painel**: modo claro/escuro + 6 cores de destaque (persistidas por usuário, remap `.dark` central em `app.css`), caminho custom do admin (`ADMIN_PATH`, default `admin`) com `admin_path()` em todas as rotas.
- **Configurações do site/SEO**: nome/logo/favicon/título/descrição/OG; `og:site_name/type/url` e defaults sobrepõem valores do tema.
- **i18n**: português (pt-BR) e inglês (en-US) no painel, instalação, login e site público; seletor em Configurações persiste `site_locale`.
- **Construtor visual (Page Builder)**: GrapesJS + Bootstrap 5 com autosave/publish, sanitização DOM de duplo estágio (publicar + render), render público standalone (chrome com header/footer/menus) e fallback para o tema quando a página não tem builder.
- **Visual Site Builder** (substitui/amplia o builder acima):
  - **Registry de componentes** com allowlist no servidor; HTML renderizado por Blade; conteúdo do usuário nunca é executado como código.
  - **18 componentes** (section, container, row, column, spacer, divider, heading, text, image, button, card, list, alert, badge, **video, youtube, blockquote, icon**).
  - **Publicação versionada** com `pages.builder_version` + `page_revisions` (snapshot da árvore + projeto GrapesJS) e restauração de revisões.
  - **Diff por linha** (`HtmlLineDiff`) entre qualquer revisão e a versão atual, com botão "Ver diferenças" no painel.
  - **Design system global** (cores, fontes, espaçamento, breakpoints) compartilhado entre editor (via breakpoints → dispositivos responsivos) e front-end; utilitários `--pb-*`/`pb-bg-*`/`pb-text-*`/`pb-pad-*`/`pb-gap-*`/`pb-bp-*`.
  - **Bindings dinâmicos**: `{site:name}`, `{page:title}`, `{page:description}`, `{year}`, `{media:ID}` resolvidos no render.
  - **Templates de site** (header/footer/not_found) editáveis no mesmo construtor, substituindo a chrome do tema enquanto ativos.
  - **Cache por página** com epoch global para invalidação segura.
- **Blog (módulo `blog`, bundled, imutável)**: `categories` + `posts` (título, slug auto/único, excerpt, corpo sanitizado, categoria FK, capa FK mídia, autor, SEO por post, `published_at`). CRUD admin sob `@can('manage-blog')`, arquivo público em `/blog` (com filtro por categoria) e artigo em `/blog/{slug}`; rascunhos e posts futuros ficam invisíveis e 404. `blog` entra em `RESERVED_SLUGS` das páginas para não sombrear a rota.
- **Verificador e atualizador de releases**:
  - `UpdateChecker` lê `releases/latest` do GitHub e cai para `/tags` quando não há Release (`source=auto|releases|tags`), comparação via `version_compare`; resultado cacheado 6h; URL aceito só se for `github.com/{repo}/releases/…`.
  - Banner só-admin no layout do painel (`View::composer`) com link ao release, "Verificar novamente" e "Dispensar esta versão".
  - `sgcore:check-updates` agendado (`Schedule::command(...)->dailyAt('04:00')`) — precisa do cron `schedule:run`.
  - **Atualizador opt-in one-click** (`sgcore:update` + botão "Aplicar" com confirmação datilografada): modo de manutenção, backup do banco (mysqldump/pg_dump/cópia sqlite), `git fetch`+`git checkout` da tag, `composer install --no-dev`, `migrate --force`, `optimize:clear`, `up`, e **rollback automático** em falha. Desligado por padrão (`CMS_UPDATER_ALLOW_APPLY=false`).
  - `sgcore:update --dry-run` mostra o plano e os commits de entrada sem tocar em nada.
- **Ferramental dev**: `sgcore:serve` / `composer serve` que mata workers `php -S` órfãos da porta antes de subir, evitando deadlock do built-in server no Windows.
- **`composer setup`** — script que instala dependências PHP/JS, copia `.env`, gera a `APP_KEY`, roda migrations e `npm run build`.
- **Responsivo do admin**: sidebar vira gaveta com toggle no mobile (overlay, ESC, fecha-ao-navegar), estática ≥ `lg`.
- **Suite de testes**: 300 testes, 1221 asserções (PHPUnit/`RefreshDatabase`, sqlite `:memory:`), Pint limpo.

### Segurança
- Sanitização por allowlist em todo conteúdo HTML rico (páginas, blog, builder, temas) — scripts/estilos/`javascript:`/`onerror`/etc. removidos.
- Proteção Zip-Slip no importador de temas e módulos.
- Guard de traversal em `GET /media/{path}`.
- Componentes de UI de terceiros só por allowlist (`icon` usa paths fixos de `config('pagebuilder.icons')`; `youtube` valida ID `^[A-Za-z0-9_-]{11}$` e monta iframe para `youtube-nocookie.com`).
- `APP_ENV=production`/`APP_DEBUG=false` por padrão em novas instalações (`cms:install` e web).

## [0.x] — Rascunhos de desenvolvimento (pré-1.0.0)

Histórico interno durante as Fases 1–9 (scaffold modular → auth → mídia/galerias → páginas/menus → engine de temas → config admin → slots → site/SEO → revisão final) — consolidado em `9d764f4` (2026-10-08). Não é uma release pública; listado apenas por rastreabilidade.

[Unreleased]: https://github.com/seerjo0/sgcore/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/seerjo0/sgcore/releases/tag/v1.0.0
