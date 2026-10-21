# sgcore v1.0.1

Melhorias no Construtor de Sites, páginas de erro personalizadas, e um passo de release. Distribuição Git (`git clone`/tag) + pacote autocontido `sgcore-1.0.1-public_html.zip` para shared hosting.

## Destaques
- **Construtor muito mais completo**: biblioteca de blocos/presets (Hero, Recursos, CTA, Preços, Galeria, Estatísticas, Equipe, Rodapé, Depoimento), componente **HTML custom (Embed)** sanitizado, **CSS por página**, **Pré-visualizar / Ver publicado / Descartar rascunho**, navegador de **Camadas** (reordenar blocos), **Copiar/Colar estilo**, **upload de mídia dentro do editor** (modal nativo), ícones próprios por bloco e painel mais legível.
- **Correção do salvamento (HTTP 422)**: autosave/publicar agora aceitam o formato por-páginas que o GrapesJS ≥ 0.21 produz. Avisos do editor passaram a mostrar a causa real (status HTTP + mensagem; sessão expirada; erro de rede com host) e os endpoints viraram same-origin.
- **Telas de erro da marca sgcore** (404/403/419/429/500/503/…), substituindo as do Laravel; em `APP_DEBUG=true` mostram exceção, `arquivo:linha`, trecho do código, stack e dados da requisição.
- **Importar página do tema para o construtor**: converte um template HTML do tema em blocos nativos editáveis (descarta o chrome; mapeia `data-cms-slot` para bindings). Botão **Converter e editar** em Aparência.
- **`sgcore:package`** desbloqueado (colisão de `--version` → `--pkg-version`).

## Upgrade
```bash
git fetch --tags && git checkout v1.0.1
composer install --no-dev --optimize-autoloader   # apenas via Git
php artisan migrate --force                        # adiciona pages.builder_custom_css
php artisan optimize:clear
```
- **Hospedagem compartilhada**: baixe `sgcore-1.0.1-public_html.zip`, extraia (ou use o **Aplicar atualização → Pacote .zip** no painel) e rode o assistente/migrations normalmente.
- Sem renomeação de variáveis de ambiente obrigatória. Novos endpoints do construtor usam `manage-pages`; upload de mídia exige `manage-media`; a importação tema→construtor exige `manage-appearance` + `manage-pages`.

## Requisitos
- PHP **≥ 8.4.1** (uma dependência fixou o piso; 8.3 pode falhar no `platform_check` do composer), com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.
- MySQL/MariaDB. Node 20 só para (re)build de assets.

## Changelog completo
Ver seção `[1.0.1] — 2026-10-21` em `CHANGELOG.md` e o diff da tag:
https://github.com/seerjo0/sgcore/compare/v1.0.0...v1.0.1
