# sgcore v1.5.0

Versão menor: o SMTP configurado na v1.4.0 agora **tem para quem falar** — as duas features que usam e-mail chegaram: **formulário de contato no construtor** e **recuperação de senha no login**. Sem migration nova.

## Adicionado

- **Bloco "Formulário de contato"** (palet do construtor, categoria Componentes): arraste para qualquer página pública. No painel de Propriedades você edita o **título**, o **rótulo do botão** e o **assunto do e-mail** recebido. O envio usa o SMTP configurado em *Configurações → E-mail* e entrega para o **Destinatário dos formulários** (novo campo; vazio = usa o próprio remetente). O visitante vira **Reply-To** — é só responder ao e-mail. Anti-spam triplo: CSRF por sessão, limite de 8 envios/min por IP e **honeypot silencioso** (robô que preenche o campo invisível recebe sucesso falso; nada sai). Validação em pt-BR com o conteúdo preservado no formulário e alertas de sucesso/erro na página.
- **"Esqueci minha senha"** no login: o usuário informa o e-mail e recebe um **link de redefinição** gerado pelo broker (expira em 60 min, uso único), entregue pelo SMTP do painel. E-mail inexistente ou conta inativa recebem exatamente a mesma resposta — ninguém consegue descobrir quais contas existem. Ao concluir a redefinição o usuário já entra autenticado no painel.

## Correções de base que vieram junto

- Formulários de contato deixam de ser servidos pelo cache de documento do builder (o token CSRF e os alertas são por visitante — páginas com o bloco são renderizadas ao vivo; as demais seguem cacheadas como antes).

## Upgrade

Painel → *Configurações → Atualizações* → **Verificar** → **Baixar** → **Instalar agora** (clique único também funciona a partir da r1). Nenhuma migration — só arquivos e cache.

Depois de atualizar:
1. *Configurações → E-mail*: confirme o **Destinatário dos formulários** (ex.: o seu e-mail) e use **Enviar e-mail de teste**.
2. No construtor, arraste o bloco **Formulário de contato** para a página de contato e publique — o botão "Converter todas as telas" do tema também já cria páginas prontas para editar.
3. Na tela de login, o link **Esqueci minha senha** já está ativo.

Confirme `CMS_VERSION` no `.env` (`1.5.0`, ou remova a linha para usar o padrão).

## Requisitos
- PHP **≥ 8.4.1** com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.
- MySQL/MariaDB; Node 20+ apenas para recompilar assets (o pacote já vai compilado).

## Changelog completo
Ver `[1.5.0] — 2026-11-09` em `CHANGELOG.md` e o diff:
https://github.com/seerjo0/sgcore/compare/v1.4.0...v1.5.0
