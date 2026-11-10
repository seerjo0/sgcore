# sgcore v1.5.1

Patch de **fidelidade do construtor**: o Pré-visualizar passa a mostrar suas edições na hora e o canvas nunca mais exibe "HTML cru". Sem migration nova.

## Corrigido

- **Pré-visualizar/publicar ignoravam as edições mais recentes.** No GrapesJS 0.22, a digitação num bloco editável escreve direto no DOM e o model só é atualizado quando você sai do bloco (blur) — mas o preview renderiza a árvore JSON enviada ao servidor, que ainda tinha o texto antigo. O editor agora força o flush oficial (`sync:content`) antes de autosave, Pré-visualizar e Publicar; uma digitação longa também autosava continuamente. Bônus: o flush funciona até com a aba minimizada (a versão anterior usava um mecanismo que congela em background).
- **Canvas com HTML cru.** O CSS próprio da página — que, nas páginas convertadas de um tema, É a folha de estilos do tema — agora é carregado dentro do iframe do editor (junto com um visual baseline para seções, containers, títulos, formulários, cards e botões). O que você edita no canvas fica com a mesma cara do preview e do site publicado. O HTML publicado não muda.

## Upgrade

Painel → *Configurações → Atualizações* → **Verificar** → **Baixar** → **Instalar agora** (clique único também). Nenhuma migration — só arquivos e cache.

Após atualizar, **faça force refresh no navegador do painel (Ctrl+F5)** — o bundle do editor foi re-gerado com novo hash. Depois teste: edite um texto, clique logo em Pré-visualizar — a edição aparece; converta um tema e abra no construtor — o canvas já mostra o estilo do tema.

Confirme `CMS_VERSION` no `.env` (`1.5.1`, ou remova a linha para usar o padrão).

## Conteúdo (recap)

- **v1.5.0**: Formulário de contato no construtor (com SMTP do painel, honeypot, throttle, Reply-To) + Esqueci minha senha.
- **v1.4.0**: E-mail (SMTP) configurável no painel.
- **linha 1.3.x**: conversão fiel de tema + "Converter todas as telas", update por pacote destravado (causa raiz do "exit 1"), upload de mídia com transparência, Site Builder sem "código HTML puro".

## Requisitos
- PHP **≥ 8.4.1** com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.
- MySQL/MariaDB; Node 20+ apenas para recompilar assets (o pacote já vai compilado).

## Changelog completo
Ver `[1.5.1] — 2026-11-10` em `CHANGELOG.md` e o diff:
https://github.com/seerjo0/sgcore/compare/v1.5.0...v1.5.1
