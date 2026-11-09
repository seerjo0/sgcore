# sgcore v1.2.3

Correção pontual no Site Builder: o "código HTML puro" no Pré-visualizar e nas páginas aninhadas, e a conversão de tema pelo menu Aparência que abria blocos quebrados. Sem migration nova.

## Corrigido

- **Pré-visualização e páginas aninhadas exibiam código HTML puro.** Os componentes de layout (`section`, `container`, `row`, `column`, `card`) embrulhavam os filhos já renderizados com `{{ $children }}` — o Blade escapava o markup. Resultado: qualquer página com blocos aninhados (toda conversão de tema, hero com container dentro do section, card com heading dentro etc.) saía no "Pré-visualizar" do construtor e no site público como **código-fonte HTML literal**, com o escape dobrando a cada nível. Agora os filhos são emitidos crus (`{!! $children !!}`), seguro por construção: cada nó já passou pelo registry allowlist e a própria view-folha sanitiza seu conteúdo.
- **Conversão de tema para o construtor quebrava a página.** O importador (1) vazava o `<head>` do template como blocos de "HTML custom" — `<meta>`, `<title>`, `<link>` poluindo o canvas, porque o parser achata `html/head/body` quando o arquivo é embrulhado — e (2) não escrevia o campo `type` nos nós, enquanto o `loadProjectData` do GrapesJS **não** executa a detecção por atributo em JSON: os blocos abriam como `divs` mudas, sem traits e sem painel de Propriedades. Agora cada nó sai tipado como o editor registra (`pb-<tipo>`; botão = built-in `link`, imagem = built-in `image`), os tags de head são descartados na leitura, o bloco de HTML importado mostra o mesmo placeholder do bloco arrastado do palet, e o slot com typo `site.nam` saiu de vez.

## Upgrade

Do painel, em *Configurações → Atualizações*: **Baixar** e depois **Atualizar com progresso** (a tela de progresso já existia na 1.2.2 e continua igual). Nenhuma migration — basta deixar o updater rodar e, ao terminar, confirmar a versão `1.2.3` em *Configurações*.

- **Páginas do construtor se curam sozinhas**: o site público é reconstruído da árvore de componentes a cada render, então páginas que saíam como código-fonte voltam a renderizar normalmente sem republish (no máximo aguarde o cache de 1 h ou republique a página para valer na hora).
- **Conversões antigas**: o fix de tipagem age na *conversão*. Rascunhos já salvos no banco antes dela continuam com os nós sem `type` (blocos abrem sem Propriedades). Se precisar editar uma dessas: reconverta o tema em Aparência e replique os ajustes, ou edite pelo canvas. Página nunca publicada não perde nada.
- O histórico de revisões (diff) de versões anteriores à 1.2.3 continua mostrando o snapshot escapado da época — é foto do passado, não afeta o presente.

Confirme `CMS_VERSION` no `.env` (deve ser `1.2.3`, ou remova a linha para usar o padrão).

## Requisitos
- PHP **≥ 8.4.1** com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.
- MySQL/MariaDB; Node 20+ apenas para recompilar assets (o pacote já vai compilado).

## Changelog completo
Ver `[1.2.3] — 2026-11-09` em `CHANGELOG.md` e o diff:
https://github.com/seerjo0/sgcore/compare/v1.2.2...v1.2.3
