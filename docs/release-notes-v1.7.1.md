# sgcore v1.7.1

Patch de **usabilidade**: os uploads de ZIP do painel agora têm um botão claro de "escolher arquivo", igual ao da Biblioteca de mídia. Sem migration nova.

## Corrigido

- **Botão explícito de escolher arquivo em Módulos, Aparência e Atualizações.** Quem enviava um tema, um módulo ou um pacote `.zip` via Atualizações só via o campo nativo discreto do navegador — sem nada óbvio para clicar. Agora os três compartilharam o mesmo estilo da tela de **Mídia**: botão colorido (cor primária do tema, fonte em negrito) ao lado do nome do arquivo escolhido. Nada muda no que já é aceito (mesmo ZIP, mesmos limites): fica só mais evidente **onde clicar** para escolher o arquivo.
  - Em **Atualizações**, o formulário "Enviar e atualizar" (pacote offline) continua aparecendo apenas quando o ambiente permite aplicar atualização — a mesma regra dos botões Baixar/Instalar.

## Upgrade

Painel → *Configurações → Atualizações* → **Verificar** → **Baixar** → **Instalar agora**. Nenhuma migration — só arquivos e cache. Depois de atualizar, **Ctrl+F5** no navegador do painel (assets re-gerados). Confirme `CMS_VERSION` no `.env` (`1.7.1`, ou remova a linha para usar o padrão).

## Requisitos
- PHP **≥ 8.4.1** com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.
- MySQL/MariaDB; Node 20+ apenas para recompilar assets (o pacote já vai compilado).

## Changelog completo
Ver `[1.7.1] — 2026-11-25` em `CHANGELOG.md` e o diff:
https://github.com/seerjo0/sgcore/compare/v1.7.0...v1.7.1
