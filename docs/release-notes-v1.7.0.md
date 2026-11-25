# sgcore v1.7.0

Versão menor: **gestão do que está instalado** — excluir temas pela interface e atualizar tema/módulo reenviando o ZIP. Sem migration nova.

## Adicionado

- **Excluir tema** — em *Configurações → Aparência*, cada tema importado que não esteja em uso ganhou o botão **Excluir tema** (com confirmação). A pasta do tema sai do storage e os valores de slots daquele tema nas páginas são limpos. As páginas já convertidas pelo construtor **continuam funcionando** (não dependem mais do tema). Temas embutidos não podem ser excluídos; se o tema estiver ativo, ative outro antes. Tema quebrado por ZIP inválido? Excluir é justamente o caminho.
- **Reenviar o ZIP atualiza no lugar** — enviar por upload um tema ou módulo cujo identificador já existe **substitui a instalação** em vez de dar "já existe". O painel responde "atualizado com o arquivo enviado". Nada de arquivo órfão do pacote antigo (a pasta inteira é trocada), e um módulo mantém seu estado (ativo/inativo) e histórico de instalação. Proteções: itens embutidos do core nunca são substituídos; ao atualizar um módulo, se uma dependência sumiu/ficou inativa o envio é **bloqueado antes** de mexer na versão boa.
- **Interface imediata após atualizar módulo** — o Blade compilado antigo é descartado na atualização; a UI nova aparece sem templates misturados.

## Upgrade

Painel → *Configurações → Atualizações* → **Verificar** → **Baixar** → **Instalar agora**. Nenhuma migration — só arquivos e cache.

Depois de atualizar, teste: *Aparência* mostra **Excluir tema** nos importados não-ativos; reenvie o ZIP de um tema qualquer (ex.: o mesmo de antes) e confira o aviso "Tema … atualizado com o arquivo enviado". Confirme `CMS_VERSION` no `.env` (`1.7.0`, ou remova a linha para usar o padrão).

## Requisitos
- PHP **≥ 8.4.1** com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.
- MySQL/MariaDB; Node 20+ apenas para recompilar assets (o pacote já vai compilado).

## Changelog completo
Ver `[1.7.0] — 2026-11-25` em `CHANGELOG.md` e o diff:
https://github.com/seerjo0/sgcore/compare/v1.6.0...v1.7.0
