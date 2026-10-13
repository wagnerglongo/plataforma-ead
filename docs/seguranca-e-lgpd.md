# Segurança e LGPD

## Autenticação e sessão

- Senhas com `password_hash` (bcrypt) e rehash automático quando o algoritmo padrão do PHP muda.
- Login com limite de 5 tentativas a cada 15 minutos por e-mail + IP; tempo de resposta igual para e-mail existente ou não.
- Recuperação de senha com token aleatório de 256 bits, guardado só como hash SHA-256, válido por 1 hora e de uso único. A resposta é a mesma exista ou não a conta.
- Cookie de sessão `HttpOnly`, `SameSite=Lax`, `Secure` em HTTPS; ID regenerado no login; expiração por inatividade (`SESSION_LIFETIME`).

## Autorização

- Middlewares por rota: `auth`, `staff`, `can:<permissão>` e `not-demo`.
- 15 permissões em 6 grupos, atribuídas a perfis editáveis no painel. O perfil Administrador tem acesso total e não pode ser editado; ninguém consegue tirar o próprio acesso.
- Vídeos (`/aluno/aula/{id}/video`) e materiais (`/aluno/material/{id}`) conferem a matrícula a cada pedido. Os arquivos ficam em `storage/app`, fora da pasta pública.

## Entrada e saída de dados

- Todas as consultas usam prepared statements (PDO sem emulação).
- Toda saída nas views passa por `e()` (`htmlspecialchars` com `ENT_QUOTES`). O texto rico dos cursos usa uma marcação mínima convertida no servidor, sem HTML livre.
- CSRF: token por sessão conferido em todo POST (campo `_token` ou cabeçalho `X-CSRF-Token`).
- Validação declarativa no servidor para todos os formulários (`App\Core\Validator`), com CPF validado pelos dígitos verificadores.
- Exportação CSV neutraliza valores que começam com `=`, `+`, `-` ou `@` (injeção de fórmula no Excel).

## Uploads

- Extensão e tipo real (`finfo`) precisam bater com a lista permitida; nome do arquivo é sempre aleatório.
- Imagens são decodificadas e regravadas pelo GD, o que remove metadados (como localização) e qualquer conteúdo escondido.
- A pasta `public/uploads` bloqueia a execução de scripts por `.htaccess`.

## Cabeçalhos

`Content-Security-Policy` com nonce por requisição (scripts inline só com o nonce; provedores de vídeo e Google listados explicitamente), `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy` e `Strict-Transport-Security` em HTTPS.

## Certificados

Cada certificado guarda uma assinatura HMAC-SHA256 de código, nome, curso, carga horária e data, feita com `APP_KEY`. A página de validação recalcula a assinatura: se alguém alterar o registro direto no banco, o certificado aparece como "não foi possível confirmar". Por isso **`APP_KEY` não deve mudar** depois que certificados forem emitidos.

## Auditoria

`audit_logs` registra login, falhas de login, alterações de cursos e aulas, matrículas, emissão e revogação de certificados, mudanças de permissões e configurações, backups, exportações e ações de LGPD, com usuário, data e IP. O painel só consulta; não há edição nem exclusão de registros.

## LGPD

| Requisito | Como a plataforma atende |
|---|---|
| Transparência (art. 9º) | Política de privacidade com finalidades e bases legais; página de cookies |
| Consentimento (art. 8º) | Termos e privacidade aceitos no cadastro; e-mails de novidades opcionais; tudo registrado em `consents` com versão, data e IP |
| Cookies de medição | Banner com "Aceitar todos" ou "Só os essenciais". GTM/GA não carregam sem aceite (Google Consent Mode v2) |
| Acesso e portabilidade (art. 18, II e V) | O aluno baixa um JSON com todos os seus dados em Privacidade e dados |
| Revogação do consentimento (art. 18, IX) | O aluno desliga os e-mails de novidades a qualquer momento |
| Eliminação (art. 18, VI) | Pedido pela conta (com senha), fila no painel com prazo de 15 dias e anonimização em um clique |
| Encarregado (art. 41) | Nome e e-mail configuráveis, exibidos na política e avisados de novos pedidos |
| Segurança (art. 46) | Itens acima, mais backups e trilha de auditoria |
| Minimização | CPF opcional e mascarado no certificado; fontes servidas localmente (sem enviar IP ao Google Fonts) |

A anonimização apaga nome, e-mail, CPF, telefone, cidade, foto e IPs dos registros, mas mantém matrículas e notas (sem identificação) para as estatísticas. Certificados já emitidos continuam validáveis, pois são documentos entregues a terceiros; essa exceção está descrita na política de privacidade.
