<?php
/** @var array $lesson @var array $module @var array $course @var array $materials */
$isNew = empty($lesson['id']);
$v = fn (string $k, $d = '') => old($k, $lesson[$k] ?? $d);
$dur = (int) ($lesson['duration_seconds'] ?? 0);
$durationText = old('duration', $dur ? clock_fmt($dur) : '');
$provider = $v('video_provider', 'youtube') ?: 'youtube';
$type = $v('type', 'video');
?>
<div class="admin-head">
  <div>
    <nav class="breadcrumb">
      <a href="<?= url('admin/cursos') ?>">Cursos</a> <?= icon('chevron-right') ?>
      <a href="<?= url('admin/cursos/' . $course['id'] . '/conteudo') ?>"><?= e($course['title']) ?></a> <?= icon('chevron-right') ?>
      <span><?= e($module['title']) ?></span>
    </nav>
    <h1><?= $isNew ? 'Nova aula' : e($lesson['title']) ?></h1>
  </div>
  <?php if (!$isNew): ?><a class="btn btn-ghost" href="<?= url('aluno/curso/' . $course['slug'] . '/aula/' . $lesson['id']) ?>" target="_blank"><?= icon('eye') ?> Ver como aluno</a><?php endif; ?>
</div>

<div class="cols">
  <form class="card card-body" method="post" action="<?= url($isNew ? 'admin/modulos/' . $module['id'] . '/aulas' : 'admin/aulas/' . $lesson['id']) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="field">
      <label for="title">Título da aula</label>
      <input class="input" id="title" name="title" value="<?= e($v('title')) ?>" required<?= invalid_attr('title') ?>>
      <?= field_error('title') ?>
    </div>
    <fieldset>
      <legend style="font-size:1rem;font-family:var(--font-body)">Tipo</legend>
      <div class="cluster">
        <label class="check"><input type="radio" name="type" value="video" <?= $type === 'video' ? 'checked' : '' ?>> Vídeo</label>
        <label class="check"><input type="radio" name="type" value="text" <?= $type === 'text' ? 'checked' : '' ?>> Texto (leitura)</label>
      </div>
    </fieldset>

    <div data-show-when="type=video">
      <div class="field">
        <label for="video_provider">Onde está o vídeo</label>
        <select class="select" id="video_provider" name="video_provider">
          <?php foreach (App\Services\VideoService::PROVIDERS as $k => $label): ?>
            <option value="<?= $k ?>" <?= $provider === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="hint">YouTube, Vimeo e arquivo enviado medem o tempo assistido. Nos outros provedores o aluno conclui pelo botão.</p>
      </div>
      <div class="field" data-show-when="video_provider=youtube,vimeo,embed">
        <label for="video_ref">Link do vídeo</label>
        <input class="input" id="video_ref" name="video_ref" value="<?= e($provider !== 'local' ? $v('video_ref') : '') ?>" placeholder="https://youtu.be/… · https://vimeo.com/… · https://player-vz-….tv.pandavideo.com.br/embed/?v=…"<?= invalid_attr('video_ref') ?>>
        <?= field_error('video_ref') ?>
      </div>
      <div class="field" data-show-when="video_provider=local">
        <label for="video_file">Arquivo do vídeo</label>
        <input class="input" type="file" id="video_file" name="video_file" accept="video/mp4,video/webm" data-duration-target="duration"<?= invalid_attr('video_file') ?>>
        <p class="hint">MP4 (H.264) ou WebM, até <?= (int) config('uploads.max_video_mb') ?> MB. <?= !$isNew && $lesson['video_provider'] === 'local' ? 'Deixe em branco para manter o vídeo atual.' : '' ?> Para muitos alunos ou vídeos longos, prefira um provedor de streaming (Panda Video ou Bunny Stream).</p>
        <?= field_error('video_file') ?>
      </div>
      <div class="field" style="max-width:220px">
        <label for="duration">Duração (min:seg)</label>
        <input class="input" id="duration" name="duration" value="<?= e($durationText) ?>" placeholder="12:30" inputmode="numeric"<?= invalid_attr('duration') ?>>
        <p class="hint">Preenchida sozinha ao escolher um arquivo.</p>
        <?= field_error('duration') ?>
      </div>
    </div>

    <div class="field">
      <label for="content"><span data-show-when="type=video">Descrição e anotações da aula</span><span data-show-when="type=text">Conteúdo da aula</span></label>
      <textarea class="textarea" id="content" name="content" rows="12"><?= e($v('content')) ?></textarea>
      <p class="hint">Parágrafos separados por linha em branco. "## " no início vira subtítulo, "- " vira lista e **texto** fica em negrito.</p>
    </div>
    <label class="check"><input type="checkbox" name="is_preview" value="1" <?= $v('is_preview') ? 'checked' : '' ?>> Aula gratuita: qualquer visitante pode assistir na página do curso</label>
    <div class="form-actions">
      <button class="btn btn-lg" type="submit" data-loading="Salvando…"><?= $isNew ? 'Criar aula' : 'Salvar aula' ?></button>
      <a class="btn btn-ghost" href="<?= url('admin/cursos/' . $course['id'] . '/conteudo') ?>">Voltar ao conteúdo</a>
    </div>
  </form>

  <div>
    <?php if (!$isNew): ?>
      <section class="card" id="materiais">
        <header class="card-head"><div><h2>Materiais complementares</h2><p>PDF, Word, Excel, PowerPoint, ZIP… até <?= (int) config('uploads.max_material_mb') ?> MB</p></div></header>
        <?php if ($materials): ?>
          <ul class="builder-items">
            <?php foreach ($materials as $m): ?>
              <li>
                <?= icon('paperclip') ?>
                <span class="grow"><strong><?= e($m['title']) ?></strong><br><small><?= e($m['original_name']) ?> · <?= bytes_fmt((int) $m['size_bytes']) ?></small></span>
                <form method="post" action="<?= url('admin/materiais/' . $m['id'] . '/excluir') ?>" data-confirm="Remover este material?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash') ?><span class="sr-only">Remover</span></button></form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <form class="card-body" method="post" action="<?= url('admin/aulas/' . $lesson['id'] . '/materiais') ?>" enctype="multipart/form-data" style="border-top:1px solid var(--rule)">
          <?= csrf_field() ?>
          <div class="field"><label for="mat-title">Nome do material</label><input class="input" id="mat-title" name="title" placeholder="Ex.: Apostila do módulo" required></div>
          <div class="field"><label for="mat-file">Arquivo</label><input class="input" type="file" id="mat-file" name="file" required<?= invalid_attr('file') ?>><?= field_error('file') ?></div>
          <button class="btn btn-secondary" type="submit" data-loading="Enviando…"><?= icon('upload') ?> Anexar</button>
        </form>
      </section>
      <form method="post" action="<?= url('admin/aulas/' . $lesson['id'] . '/excluir') ?>" data-confirm="Excluir esta aula? O progresso dos alunos nela também será apagado." style="margin-top:1rem;text-align:right">
        <?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit"><?= icon('trash') ?> Excluir aula</button>
      </form>
    <?php else: ?>
      <div class="card card-body"><p class="muted" style="margin:0">Depois de criar a aula você pode anexar apostilas, planilhas e outros materiais.</p></div>
    <?php endif; ?>
  </div>
</div>
