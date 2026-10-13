<?php
/**
 * Renderiza o vídeo conforme o provedor.
 * @var array $lesson @var string $src URL do arquivo para vídeos próprios
 */
$ref = (string) $lesson['video_ref'];
switch ($lesson['video_provider']):
    case 'local': ?>
      <video id="lesson-video" controls playsinline preload="metadata" controlslist="nodownload">
        <source src="<?= e($src) ?>" type="video/mp4">
        <?php if (App\Services\VideoService::hasWebm($ref)): ?><source src="<?= e($src) ?>?formato=webm" type="video/webm"><?php endif; ?>
        Seu navegador não reproduz vídeos HTML5.
      </video>
      <?php break;
    case 'youtube':
        $id = App\Services\VideoService::youtubeId($ref); ?>
      <div id="yt-player" data-video="<?= e($id) ?>">
        <iframe src="https://www.youtube-nocookie.com/embed/<?= e($id) ?>?rel=0" title="<?= e($lesson['title']) ?>" allow="accelerometer; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
      </div>
      <?php break;
    case 'vimeo':
        $id = App\Services\VideoService::vimeoId($ref); ?>
      <iframe id="vimeo-player" src="https://player.vimeo.com/video/<?= e($id) ?>?dnt=1" title="<?= e($lesson['title']) ?>" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>
      <?php break;
    default:
        $embed = App\Services\VideoService::safeEmbed($ref); ?>
      <?php if ($embed): ?>
        <iframe src="<?= e($embed) ?>" title="<?= e($lesson['title']) ?>" allow="fullscreen; picture-in-picture; encrypted-media" allowfullscreen></iframe>
      <?php else: ?>
        <div style="display:grid;place-items:center;color:#fff">Vídeo indisponível.</div>
      <?php endif; ?>
<?php endswitch; ?>
