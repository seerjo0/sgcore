# sgcore v1.4.0

Versão menor: **e-mail (SMTP) configurável pelo painel** — o caminho de disparo do site (formulários, recuperação de senha, avisos) agora se ajusta em *Configurações*, sem tocar no `.env` do servidor. Sem migration nova.

## Adicionado

- **Seção "E-mail (SMTP)" em Configurações** (somente admin):
  - **Transporte**: `Sem envio (apenas log)` ou `SMTP`. Em modo log nada é entregue (fica registrado no log do servidor) — é também o botão de "desligar" para quem tinha SMTP no `.env`.
  - **Servidor**: host, porta, criptografia (TLS recomendado / SSL / nenhuma), usuário e senha.
  - **Remetente**: e-mail e nome usados em todas as mensagens.
  - As opções **sobrepõem o `.env` em runtime** (web e linha de comando): todo envio via `Mail::` usa o servidor configurado enquanto ele estiver ativo. Nada é reescrito no `.env`.
- **Tratamento seguro da senha**: cifrada no banco (Crypt), nunca reapresentada na tela. Deixar o campo em branco ao salvar **mantém** a senha atual; o checkbox *Remover a senha salva* apaga.
- **E-mail de teste**: card de status ("SMTP configurado" / "Sem envio") + botão que envia um e-mail de verificação para o endereço do próprio usuário logado. Se o servidor recusar, a **causa real aparece na tela** (limite: 3 testes por 5 minutos).

## Upgrade

Painel → *Configurações → Atualizações* → **Verificar** → **Baixar** → **Instalar agora** (ou o clique único "Atualizar com progresso"). Nenhuma migration — só arquivos e cache.

Depois de atualizar:
1. *Configurações → E-mail (SMTP)*: preencha os dados do seu provedor.
2. Clique **Enviar e-mail de teste** — confira que chegou na sua caixa e que o badge está verde.
3. Se aparecer recusa do Gmail (código `534`), crie uma **senha de app** (Conta Google → Segurança → Senhas de app, com verificação em 2 etapas ativada) e use-a no campo de senha — ou troque o SMTP para um provedor de envio (Brevo, Mailtrap, Resend, SES…).

Confirme `CMS_VERSION` no `.env` (`1.4.0`, ou remova a linha para usar o padrão).

## Conteúdo (recap das anteriores incluídas)

- **Site Builder**: Pré-visualizar e páginas aninhadas sem "código HTML puro"; conversão de tema com blocos tipados (Propriedades funcionando) e sem lixo do `<head>`.
- **Conversão fiel de tema + "Converter todas as telas"**: páginas do tema → menu Páginas + construtor num clique, com o CSS do tema.
- **Update por pacote destravado**: clique único + migrations com causa visível ("Causa: …" no terminal de progresso).
- **Upload de mídia preserva transparência** (PNG/WebP não viram mais fundo preto).

## Requisitos
- PHP **≥ 8.4.1** com `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`, `intl`, `openssl`, `curl`.
- MySQL/MariaDB; Node 20+ apenas para recompilar assets (o pacote já vai compilado).

## Changelog completo
Ver `[1.4.0] — 2026-11-09` em `CHANGELOG.md` e o diff:
https://github.com/seerjo0/sgcore/compare/v1.3.0-r2...v1.4.0
