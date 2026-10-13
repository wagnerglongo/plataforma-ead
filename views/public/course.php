<?php
/** @var array $course @var array $curriculum @var ?array $enrollment @var ?array $preview @var int $quizzes @var array $related */
$modules = $curriculum['modules'];
$totalLessons = (int) $course['lessons_count'];
$active = $enrollment && $enrollment['status'] !== 'cancelled';
?>
<section class="course-hero">
  <div class="container">
    <div>
      <nav class="breadcrumb" aria-label="Você está em">
        <a href="<?= url('cursos') ?>">Cursos</a> <?= icon('chevron-right') ?>
        <a href="<?= url('cursos', ['categoria' => $course['category_slug']]) ?>"><?= e($course['category_name']) ?></a>
      </nav>
      <?php if ($course['status'] !== 'published'): ?><p><span class="badge badge-warn">Rascunho: só a equipe vê esta página</span></p><?php endif; ?>
      <h1><?= e($course['title']) ?></h1>
      <p class="lead"><?= e($course['summary']) ?></p>
      <dl class="facts">
        <div><dt>Carga horária</dt><dd><?= (int) $course['workload_hours'] ?> horas</dd></div>
        <div><dt>Nível</dt><dd><?= e(level_label($course['level'])) ?></dd></div>
        <div><dt>Conteúdo</dt><dd><?= count($modules) ?> módulos · <?= $totalLessons ?> aulas</dd></div>
        <div><dt>Alunos</dt><dd><?= number_format((int) $course['students_count'], 0, ',', '.') ?></dd></div>
      </dl>
    </div>

    <aside class="enroll-card" aria-label="Matrícula">
      <?php partial('partials/cover', ['course' => $course]) ?>
      <?php if ($active): ?>
        <div class="progress-label"><span>Seu progresso</span><strong><?= (int) $enrollment['progress_percent'] ?>%</strong></div>
        <div class="progress" style="--value:<?= (int) $enrollment['progress_percent'] ?>%" data-done="<?= $enrollment['status'] === 'completed' ? 1 : 0 ?>"></div>
        <a class="btn btn-lg btn-block" style="margin-top:1rem" href="<?= url('aluno/curso/' . $course['slug']) ?>">
          <?= $enrollment['status'] === 'completed' ? 'Rever o curso' : ((int) $enrollment['progress_percent'] > 0 ? 'Continuar de onde parei' : 'Começar o curso') ?>
        </a>
      <?php elseif (auth()): ?>
        <form method="post" action="<?= url('curso/' . $course['slug'] . '/matricular') ?>">
          <?= csrf_field() ?>
          <button class="btn btn-lg btn-block" type="submit" data-loading="Matriculando…"><?= $enrollment ? 'Reativar matrícula' : 'Matricular-se gratuitamente' ?></button>
        </form>
      <?php else: ?>
        <a class="btn btn-lg btn-block" href="<?= url('cadastro', ['curso' => $course['slug']]) ?>">Criar conta e matricular-se</a>
        <p class="muted" style="text-align:center;margin:.6rem 0 0;font-size:var(--step--1)">Já tem conta? <a href="<?= url('entrar') ?>">Entre aqui</a></p>
      <?php endif; ?>
      <?php if ($preview && !$active): ?>
        <a class="btn btn-secondary btn-block" style="margin-top:.6rem" href="<?= url('curso/' . $course['slug'] . '/aula-gratuita/' . $preview['id']) ?>"><?= icon('play-circle') ?> Assistir aula gratuita</a>
      <?php endif; ?>
      <ul>
        <li><?= icon('check') ?> Acesso pelo computador, tablet ou celular</li>
        <li><?= icon('check') ?> Progresso salvo: continue de onde parou</li>
        <?php if ($quizzes): ?><li><?= icon('check') ?> <?= plural($quizzes, 'avaliação', 'avaliações') ?> com correção na hora</li><?php endif; ?>
        <?php if ($course['certificate_enabled']): ?><li><?= icon('check') ?> Certificado em PDF com validação pública</li><?php endif; ?>
      </ul>
    </aside>
  </div>
</section>

<div class="container">
  <div class="course-body">
    <div>
      <?php if ($outcomes = lines($course['learning_outcomes'])): ?>
      <section>
        <h2>O que você vai aprender</h2>
        <ul class="check-list">
          <?php foreach ($outcomes as $o): ?><li><?= icon('check-circle') ?> <span><?= e($o) ?></span></li><?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <section>
        <h2>Sobre o curso</h2>
        <div class="prose"><?= rich_text($course['description']) ?></div>
      </section>

      <section>
        <div class="split" style="margin-bottom:1rem">
          <h2 style="margin:0">Conteúdo do curso</h2>
          <span class="muted"><?= count($modules) ?> módulos · <?= $totalLessons ?> aulas · <?= duration_fmt((int) $course['total_seconds']) ?> de vídeo e leitura</span>
        </div>
        <div class="curriculum">
          <?php foreach ($modules as $i => $m): ?>
            <details <?= $i === 0 ? 'open' : '' ?>>
              <summary>
                <span><?= e($m['title']) ?> <small>· <?= plural(count($m['lessons']), 'aula', 'aulas') ?></small></span>
                <?= icon('chevron-down') ?>
              </summary>
              <ol>
                <?php foreach ($m['lessons'] as $l): ?>
                  <li>
                    <?= icon($l['type'] === 'video' ? 'play-circle' : 'file-text') ?>
                    <span class="grow"><?= e($l['title']) ?></span>
                    <?php if ($l['is_preview'] && !$active): ?>
                      <a class="badge badge-marker" href="<?= url('curso/' . $course['slug'] . '/aula-gratuita/' . $l['id']) ?>">Aula gratuita</a>
                    <?php endif; ?>
                    <?php if ($l['materials_count']): ?><span class="muted" title="Materiais"><?= icon('paperclip') ?></span><?php endif; ?>
                    <small class="muted"><?= duration_fmt((int) $l['duration_seconds']) ?></small>
                  </li>
                <?php endforeach; ?>
                <?php if ($m['quiz']): ?>
                  <li><?= icon('quiz') ?> <span class="grow"><?= e($m['quiz']['title']) ?></span> <small class="muted"><?= plural((int) $m['quiz']['questions_count'], 'questão', 'questões') ?></small></li>
                <?php endif; ?>
              </ol>
            </details>
          <?php endforeach; ?>
          <?php if ($curriculum['final']): ?>
            <details>
              <summary><span><?= e($curriculum['final']['title']) ?> <small>· libera o certificado</small></span><?= icon('chevron-down') ?></summary>
              <ol><li><?= icon('award') ?> <span class="grow">Nota mínima de <?= (int) $curriculum['final']['pass_score'] ?>% para aprovação</span> <small class="muted"><?= plural((int) $curriculum['final']['questions_count'], 'questão', 'questões') ?></small></li></ol>
            </details>
          <?php endif; ?>
        </div>
      </section>
    </div>

    <div>
      <?php if ($req = lines($course['requirements'])): ?>
      <section>
        <h2>Requisitos</h2>
        <ul class="check-list"><?php foreach ($req as $r): ?><li><?= icon('check') ?> <span><?= e($r) ?></span></li><?php endforeach; ?></ul>
      </section>
      <?php endif; ?>
      <?php if ($course['target_audience']): ?>
      <section>
        <h2>Para quem é</h2>
        <p><?= e($course['target_audience']) ?></p>
      </section>
      <?php endif; ?>
      <?php if ($course['instructor_name']): ?>
      <section>
        <h2>Instrutor</h2>
        <div class="instructor">
          <span class="avatar"><?= e(initials($course['instructor_name'])) ?></span>
          <div><strong><?= e($course['instructor_name']) ?></strong>
            <?php $bio = App\Core\Database::value('SELECT bio FROM users WHERE id = :id', ['id' => $course['instructor_id']]); ?>
            <?php if ($bio): ?><p class="muted" style="margin:.2rem 0 0"><?= e($bio) ?></p><?php endif; ?>
          </div>
        </div>
      </section>
      <?php endif; ?>
      <section>
        <h2>Certificado</h2>
        <p>Ao concluir as aulas e ser aprovado nas avaliações, o certificado é emitido na hora, em PDF, com código único e QR Code. Qualquer pessoa pode confirmar a autenticidade em <a href="<?= url('validar') ?>"><?= e(preg_replace('#^https?://#', '', absolute_url('validar'))) ?></a>.</p>
      </section>
    </div>
  </div>

  <?php if ($related): ?>
  <section class="section" style="padding-top:0">
    <div class="section-head"><h2>Você também pode gostar</h2></div>
    <div class="course-grid">
      <?php foreach ($related as $r): ?><?php partial('partials/course-card', ['course' => $r]) ?><?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</div>
