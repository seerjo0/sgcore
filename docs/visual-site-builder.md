# SGCore Visual Site Builder

Guia do construtor visual de sites: componentes, publicação versionada, design
system, bindings dinâmicos, templates globais, cache e segurança.

## Arquitetura

O editor usa **GrapesJS** (Bootstrap 5 no canvas). O que é publicado **não** é
o HTML do GrapesJS: no `publish`, o `ProjectExtractor` percorre o projeto JSON e
deriva uma **árvore estruturada de componentes** (`{type, id, classes, style,
props, children}`), guardada em `pages.builder_published.components`. A página
pública é renderizada **no servidor**, via Blade, a partir dessa árvore — o
front-end nunca executa GrapesJS.

Compatibilidade retroativa: se a árvore derivada for vazia (payload antigo sem
`data-pb-type`), o publish mantém o pipeline legado (HTML sanitizado do
editor). Páginas já publicadas continuam renderizando normalmente.

## Registry de componentes

`App\Modules\PageBuilder\Services\ComponentRegistry` (singleton, registrado em
`PageBuilderServiceProvider`). Cada componente é uma instância de
`App\Modules\PageBuilder\Services\Components\Component` que declara:

- `type()` — id usado no editor como `data-pb-type`;
- `name()` / `group()` — rótulo e categoria no painel de blocos;
- `props()` — metadados das traits (`label`, `type` text/textarea/select,
  `options`), editáveis como atributos `data-pb-*`;
- `rules()` — regras Laravel de validação dos props (aplicadas no publish e no
  render);
- `view()` — template Blade `pagebuilder::components.*`.

Os 18 componentes embutidos (layout: `section`, `container`, `row`, `column`,
`spacer`, `divider`; conteúdo: `heading`, `text`, `image`, `button`, `video`,
`youtube`, `blockquote`; componentes: `card`, `list`, `alert`, `badge`, `icon`)
são instâncias de `GenericComponent` definidas em `Components::all()`.

Props especiais: `content` (texto/HTML rico) vem do conteúdo do nó GrapesJS e é
sanitizado na renderização; `src`/`href` passam por validação de esquema
(`BuilderSanitizer::safeUrl`); `style` inline é sanitizado.

Widgets de mídia são restritos por construção: `video` só emite `<video>` com
`src`/`poster` validados; `youtube` exige um ID de 11 caracteres
(`^[A-Za-z0-9_-]{11}$`) e sempre monta o `<iframe>` apontando para o host fixo
`youtube-nocookie.com` (nunca uma URL arbitrária); `icon` só renderiza um dos
paths SVG da allowlist `config('pagebuilder.icons')`, escolhidos por nome — o
HTML do usuário jamais vira SVG.

### Adicionando um widget

1. Crie o Blade `app/Modules/PageBuilder/resources/views/components/MEU_TIPO.blade.php`
   recebendo `$props`, `$children`, `$node`, `$component` (escapando com
   `{{ }}`; use `{!! !!}` apenas sobre conteúdo já sanitizado).
2. Registre no boot de um service provider (sua própria extensão de módulo):

```php
$registry = $app->make(ComponentRegistry::class);
$registry->register(new class extends Component {
    public function type(): string { return 'icon_box'; }
    public function name(): string { return \__('pagebuilder.block_icon_box'); }
    public function group(): string { return 'components'; }
    public function props(): array {
        return ['title' => ['label' => \__('pagebuilder.prop_text'), 'type' => 'text']];
    }
    public function rules(): array {
        return ['title' => ['nullable', 'string', 'max:255']];
    }
    public function view(): string { return 'pagebuilder::components.icon_box'; }
});
```

3. Dê ao editor um bloco cujo HTML carregue `data-pb-type="icon_box"` (e os
   `data-pb-*` dos props). Os metadados do registry já alimentam a geração de
   traits em `resources/js/page-builder/editor.js`.

Tipos não registrados **nunca** são renderizados: nós sem `data-pb-type` ou com
tipo desconhecido são descartados junto com a subárvore.

## Publicação versionada e revisões

- `pages.builder_version` incrementa a cada publish.
- Cada publish cria `page_revisions` (snapshot: `components`, `html`, `css`,
  `project` GrapesJS, `created_by`).
- `GET /admin/paginas/{page}/construtor/revisoes` lista; `POST
  .../revisoes/{revision}/restaurar` restaura: vira a versão publicada atual
  (com nova revisão) e recarrega o rascunho do editor a partir do `project`
  salvo. O histórico nunca é apagado.
- `GET .../revisoes/{revision}/diferencas` devolve um diff por linha
  (`{lines:[{t:add|del|same, s}], truncated}`) entre a revisão e a versão atual,
  calculado por `HtmlLineDiff` (HTML fatiado em tags + LCS com aparo de
  prefixo/sufixo e orçamento de 600 linhas). No editor, o painel **Histórico**
  mostra um botão *Diferenças* por revisão.
- O payload publicado atual carrega `version` (schema, `config.pagebuilder
  .schema_version`) e `published_at`.

## Renderização pública e cache

`Renderer::render(Page)` monta o documento standalone (`<head>` com SEO/OG,
Bootstrap via `config('pagebuilder.bootstrap_css')`, CSS do editor + tokens do
design system, header/footer) e cacheia por
`pb:doc:{id}:v{version}:p{epoch}` (`cache_ttl_seconds`). Invalidação:

- publish/restaurar de página → nova `builder_version` (chave muda);
- publish/ativação de template e save do design system →
  `Renderer::purge()` incrementa o epoch global (`pb-purge-epoch`),
  abandonando todos os documentos e templates em cache.

Renders de template usam `pb:tpl:{type}:v{version}:p{epoch}`.

Em produção com `CACHE_STORE=file/database` o cache sobrevive a requests; com
`array` (testes) por processo.

## Design system

Persistido na setting `design_system` (cores, fontes, escala de espaçamento e
breakpoints). Editável em **Modelos do construtor** → *Identidade visual*
(`POST /admin/construtor/modelos/identidade`) — o formulário já expõe os quatro
espaçamentos (`xs/sm/md/lg`) e os quatro breakpoints (`sm/md/lg/xl`), validados
como comprimentos CSS (`^\d+(\.\d+)?(px|rem|em|%)$`) com *fallback* para o
default em caso de valor inválido. O CSS resultante (`--pb-primary` etc.,
`--pb-space-*`, `--pb-bp-*` + utilitários `pb-bg-*`/`pb-text-*`/`pb-font-*`/
`pb-pad-*`/`pb-gap-*`) é injetado em todas as páginas construídas e no canvas
do editor; os **breakpoints** alimentam larguras dos dispositivos responsivos do
editor (desktop/tablet/mobile), e as cores/fontes ficam disponíveis como
variáveis CSS e classes utilitárias.

## Bindings dinâmicos

`RenderContext` resolve nos props, antes da renderização: `{site:name}`,
`{page:title}`, `{page:description}`, `{year}` e `{media:ID}` (URL da
biblioteca). Tokens desconhecidos permanecem como texto (e são escapados).

## Theme builder (templates globais)

Tabela `builder_templates` com os tipos `header`, `footer`, `not_found`
(`config('pagebuilder.templates')`). Editados no mesmo construtor
(`GET /admin/construtor/modelos/{type}`), exigem pelo menos um componente
registrado no publish. Quando **ativados**:

- `header`/`footer` substituem a chrome padrão (menus primário/rodapé) em
  todas as páginas do construtor;
- `not_found` substitui o 404 do tema (`SiteController::notFound`);

desativados, o tema permanece no controle. Qualquer alteração `purge()`ia o
cache.

## Segurança

- Allowlist de tipos (registry) e de tags/atributos/esquemas de URL e CSS
  (`BuilderSanitizer`, reaplicada no render inclusive sobre o HTML legado).
- Props validados por regras Laravel no publish **e** no render; nó inválido é
  pulado silenciosamente.
- Nenhum PHP/HTML do usuário é "executado": o output Blade é o único caminho;
  `{{ }}` escapa, `{!! !!}` só recebe sanitizado.
- Autorização: todas as rotas sob `can:manage-pages`.

## Limitações conhecidas

- O diff de revisões é por linha/tag (não por árvore de componentes): mostra
  linhas adicionadas/removidas, não um destaque semântico de props.
- `not_found`/header/footer substituem a chrome apenas em páginas do
  construtor; o tema clássico segue governando páginas não construídas.
- Páginas com payload legado continuam usando o HTML sanitizado do editor
  (sem editor de componentes tipados) até uma nova publicação.
