# Integrações

## Vídeo

Escolhido por aula, em Cursos › Conteúdo › Aula › "Onde está o vídeo".

| Provedor | Como cadastrar | Tempo assistido medido? | Quando usar |
|---|---|---|---|
| Arquivo enviado | Upload de MP4 (H.264) ou WebM | Sim | Poucos alunos e vídeos curtos. Consome espaço e banda da hospedagem |
| YouTube | Link do vídeo (pode ser "não listado") | Sim, pela IFrame API | Conteúdo gratuito ou sem necessidade de proteção |
| Vimeo | Link do vídeo | Sim, pela Player API | Vídeos privados com domínio restrito |
| Panda Video | Link de incorporação (`https://player-vz-….tv.pandavideo.com.br/embed/?v=…`) | Não: conclusão pelo botão | Proteção contra download, DRM e boa entrega no Brasil |
| Bunny Stream | Link de incorporação (`https://iframe.mediadelivery.net/embed/…`) | Não: conclusão pelo botão | Custo baixo por GB entregue |

Para um arquivo enviado, a plataforma aceita uma versão `.webm` com o mesmo nome ao lado do `.mp4` em `storage/app` como fonte alternativa.

Para adicionar outro provedor de incorporação, inclua o domínio em `VideoService::EMBED_HOSTS` e na diretiva `frame-src` da CSP em `App\Core\App::securityHeaders()`.

## E-mail

`.env`:

```
MAIL_DRIVER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl        # ou tls na porta 587
MAIL_USERNAME=nao-responda@seu-dominio.com.br
MAIL_PASSWORD=...
MAIL_FROM=nao-responda@seu-dominio.com.br
MAIL_FROM_NAME="Nome da instituição"
```

Com `MAIL_DRIVER=log`, as mensagens vão para `storage/logs/mail.log` (desenvolvimento). E-mails enviados: boas-vindas, convite de conta criada pela equipe, redefinição de senha, aviso de pedido de exclusão ao encarregado e confirmação de exclusão ao titular.

Para e-mail marketing (Mailchimp, RD Station, Brevo), exporte em Alunos › Exportar CSV: a coluna "Aceita e-mails" separa quem consentiu.

## Google Tag Manager e Analytics

Em Configurações, preencha o ID do GTM (`GTM-XXXXXXX`) ou do GA4 (`G-XXXXXXXXXX`). Os scripts só carregam depois do aceite de cookies, com Consent Mode v2 (`analytics_storage`, `ad_storage`, `ad_user_data`, `ad_personalization`).

Eventos enviados ao `dataLayer`, prontos para gatilhos no GTM e conversões no Google Ads ou Meta:

| Evento | Quando | Parâmetros |
|---|---|---|
| `sign_up` | Conta criada | — |
| `enroll` | Matrícula feita | `course` |
| `course_complete` | Curso concluído | `course` |

## WhatsApp

Em Configurações, informe o número com DDI e DDD (`5517999990000`). O botão flutuante aparece em todo o site com uma mensagem inicial pronta.

## Próximas integrações previstas na arquitetura

- **Pagamentos** (Mercado Pago, Pagar.me, Stripe): o ponto de entrada é `CatalogController::enroll`, que hoje cria a matrícula direto.
- **SSO / login social**: `Auth::login()` recebe o usuário já identificado.
- **API para outros sistemas**: o roteador já devolve JSON (`Response::json`) e o middleware de autenticação responde 401 em JSON.
