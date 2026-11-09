# sgcore v1.2.3-r1

Revisão 1 da **1.2.3**: devolve o "Atualizar com progresso" (clique único) ao funcionamento. Sem migration nova. Traz tudo o que a 1.2.3 trazia (fix do Site Builder).

## Corrigido

- **Clique único em "Atualizar com progresso" falhava com "Nenhum pacote baixado."** A tela de progresso é aberta com a palavra `?modo=atualizar`, mas o JavaScript da própria página devolve ao stream o valor **interno** já resolvido (`modo=update`). O controller comparava apenas com a palavra `atualizar` — e quem não era `'atualizar'` caía no modo `install`, que exige um pacote **já baixado** na prateleira. Como o clique único baixava e aplicava dentro do stream, não havia pacote: a atualização morria logo no início com *"Update failed: No package downloaded. Rollback was attempted."*. O caminho em dois cliques (**Baixar** → **Instalar agora**) nunca foi afetado — por isso os testes não pegaram (eles postavam `instalar`). Agora os dois vocabulários valem: `atualizar`/`update` e `instalar`/`install`, e um teste novo reproduz exatamente o round-trip da página (GET com a palavra, POST com o valor interno, NDJSON com download + apply).

## Upgrade

**Se você está na 1.2.2 ou na 1.2.3 e o clique único travou com "Nenhum pacote baixado."**, use um dos caminhos que sempre funcionaram:

1. **Baixar → Instalar agora** em *Configurações → Atualizações* (dois cliques; o "Baixar" põe o pacote na prateleira e o "Instalar agora" aplica com progresso).
2. **Atualização manual → Pacote .zip**: envie o `sgcore-1.2.3-r1-public_html.zip` baixado da Release.
3. Extração manual pelo File Manager (por cima, preservando `.env`/`storage`; confira o bloco `# php -- BEGIN cPanel-generated handler` no `.htaccess`).

Nenhuma migration. Depois de aplicar, confirme `1.2.3-r1` em *Configurações* — o `CMS_VERSION` do `.env`, se existir, deve bater (ou remova a linha para usar o padrão).

## Conteúdo desta revisão (recap da 1.2.3)

- **Site Builder**: pré-visualizar/site público não mostram mais o código-fonte HTML em páginas aninhadas (views de layout emitindo os filhos crus); conversão de tema pelo menu Aparência gera blocos **tipados** (Propriedades funcionando), sem lixo do `<head>` no canvas.

## Requisitos
- PHP **≥ 8.4.1** com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.
- MySQL/MariaDB; Node 20+ apenas para recompilar assets (o pacote já vai compilado).

## Changelog completo
Ver `[1.2.3-r1] — 2026-11-09` e `[1.2.3] — 2026-11-09` em `CHANGELOG.md` e o diff:
https://github.com/seerjo0/sgcore/compare/v1.2.3...v1.2.3-r1
