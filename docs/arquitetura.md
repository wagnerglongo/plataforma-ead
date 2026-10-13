# Arquitetura

## Pastas

```
app/
  Core/         núcleo: App (front controller), Router, Request/Response, View, Database (PDO),
                Session, Auth, Csrf, Validator, Upload, Mailer, Audit, RateLimiter, Settings
  Controllers/  públicos (Home, Catalog, Auth, Certificate, Page, Install)
    Student/    área do aluno (Dashboard, Player, Quiz, Certificate, Profile, Privacy)
    Admin/      painel (Dashboard, Course, Curriculum, Lesson, Quiz, Category, Student,
                Certificate, Report, Team, Role, Settings, Audit, Backup, Lgpd)
  Models/       consultas do catálogo (Course)
  Services/     regras de negócio: Progress, Certificate, Quiz, Report, Lgpd, Backup, Video, Installer
  Lib/          componentes sem dependência: QrCode, PdfDocument, CertificateRenderer, Chart, FileStreamer
  helpers.php   funções de apoio às views (e(), url(), asset(), date_br()…)
bin/ead         linha de comando (install, migrate, demo, backup, restore, key, check)
config/app.php  configuração lida do .env
database/       schema.sql, migrations/, seeds/content.php (conteúdo de demonstração)
public/         única pasta exposta à web: index.php, assets/, uploads/ (imagens públicas)
resources/fonts fontes TTF usadas no certificado (licença OFL)
routes/web.php  todas as rotas e seus middlewares
storage/        privado: app/ (vídeos e materiais), logs/, cache/, sessions/, backups/
tests/          smoke.php e testes ponta a ponta (Playwright)
views/          templates PHP: layouts/, partials/, public/, auth/, student/, admin/
```

## Fluxo de uma requisição

1. `public/index.php` carrega `app/bootstrap.php` (autoload PSR-4 próprio, `.env`, fuso, tratamento de erros).
2. `App::run()` inicia a sessão, monta o `Router` com `routes/web.php` e encontra a rota.
3. Em todo método diferente de GET, o token CSRF é conferido.
4. Os middlewares da rota rodam em ordem: `auth`, `guest`, `staff`, `can:<permissão>`, `not-demo`.
5. O controller devolve uma `Response` (HTML, JSON, redirecionamento, download ou stream).
6. Exceções viram respostas: `ValidationException` volta ao formulário com erros e dados digitados; `HttpException` mostra a página de erro; qualquer outra é registrada em `storage/logs` e mostra um erro 500 genérico.
7. Cabeçalhos de segurança (CSP com nonce, HSTS, X-Frame-Options…) são aplicados em todas as respostas.

## Modelo de dados

```
roles ─< role_permissions >─ permissions
roles ─< users ─< enrollments >─ courses ─< modules ─< lessons ─< materials
                  users ─< lesson_progress >─ lessons
courses ─< quizzes ─< questions ─< question_options
users ─< quiz_attempts >─ quizzes
users ─< certificates >─ courses        (cópia dos dados + assinatura HMAC)
users ─< consents, data_requests, audit_logs, password_resets
categories ─< courses        settings (chave/valor)        login_attempts
```

- `quizzes.module_id` nulo = avaliação final do curso.
- `enrollments.progress_percent` é um valor calculado e guardado para listar rápido; a fonte da verdade são `lesson_progress` e `quiz_attempts`.
- `certificates` guarda nome, curso e carga horária **da emissão**: renomear o curso depois não altera documentos emitidos.

## Regras de progresso (`ProgressService`)

- O player envia a cada ~10 s a posição atual e quantos segundos foram **reproduzidos** desde o último envio. Saltos na barra não somam (o JavaScript só conta diferenças menores que 1,5 s entre atualizações e o servidor limita cada envio a 30 s).
- Aula em vídeo conclui com 85% da duração assistida. Aula em texto ou vídeo incorporado sem API conclui pelo botão.
- Teste do módulo libera quando as aulas do módulo estão concluídas; a avaliação final, quando tudo antes dela está concluído.
- O curso conclui quando todas as aulas estão concluídas e todas as avaliações aprovadas. Nesse momento a matrícula vira `completed` e o certificado é emitido.
- "Continuar de onde parou" usa a última aula acessada (se ainda não concluída, na posição salva) ou o primeiro item pendente.

## Decisões

- **Sem framework e sem Composer.** Pedido do projeto e vantagem real em hospedagem compartilhada: basta enviar os arquivos. O núcleo (`app/Core`) tem cerca de 1.500 linhas e segue convenções conhecidas (front controller, middlewares, PSR-4), então outro desenvolvedor se localiza rápido.
- **Certificado como imagem dentro do PDF.** Permite tipografia de qualidade com fontes TTF via GD sem uma biblioteca de PDF. O QR Code no PDF também é clicável.
- **Gráficos em SVG gerados no servidor.** Leves, acessíveis (`role="img"` com rótulo) e sem JavaScript.
- **Vídeo próprio com fonte alternativa WebM.** O MP4 (H.264) cobre os navegadores comerciais; o WebM cobre os navegadores sem H.264. Para escala, a recomendação é um provedor de streaming (ver integrações).
