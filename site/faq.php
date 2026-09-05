<?php
declare(strict_types=1);
$page = 'faq';
$description = 'Questions fréquentes sur les vols découverte, les baptêmes de l’air, les bons cadeaux et la formation au pilotage au Saumur Air Club.';
require __DIR__ . '/inc/header.php';

$hero = [
  'page'         => true,
  'cle_image'    => 'faq.hero.image',
  'image'        => '/assets/img/faq-hero.jpg',
  'alt'          => 'Un avion léger en vol au coucher du soleil',
  'titre'        => 'Questions fréquentes',
  'cle_titre'    => 'faq.hero.titre',
  'accroche'     => 'Tout ce qu’il faut savoir avant de prendre les airs.',
  'cle_accroche' => 'faq.hero.accroche',
];
require __DIR__ . '/inc/hero.php';

/* Questions / réponses. Modifiables une à une depuis le back-office.
   Servent aussi aux données structurées (référencement). */
$faq = [
  [
    'q' => 'Qu’est-ce qu’un vol découverte au Saumur Air Club ?',
    'r' => 'Le vol découverte est un baptême de l’air : vous êtes passager à bord de l’un de '
         . 'nos avions et survolez le Val de Loire pendant environ 30 minutes — les châteaux, '
         . 'le vignoble et les reflets de la Loire. Un pilote expérimenté est aux commandes.',
  ],
  [
    'q' => 'Quelle est la différence entre un vol découverte et un vol d’initiation ?',
    'r' => 'Pendant le vol découverte, vous profitez du paysage en passager. Le vol d’initiation, '
         . 'lui, vous met aux commandes : accompagné d’un instructeur, vous pilotez réellement '
         . 'l’appareil. C’est souvent le premier pas vers le brevet de pilote.',
  ],
  [
    'q' => 'Combien coûte un vol découverte ?',
    'r' => 'Le vol découverte de 30 minutes est à 130 € pour un passager, 180 € pour deux et '
         . '240 € pour trois. Le tarif s’entend pour l’ensemble du vol : à trois, cela revient '
         . 'à 80 € par personne.',
  ],
  [
    'q' => 'Combien de personnes peuvent embarquer en même temps ?',
    'r' => 'Jusqu’à trois passagers selon l’avion utilisé. Le pilote répartit les places en '
         . 'fonction du poids de chacun, pour respecter l’équilibrage de l’appareil.',
  ],
  [
    'q' => 'Comment offrir un vol découverte en bon cadeau ?',
    'r' => 'Vous commandez le bon cadeau en ligne sur la page Vols découvertes. Vous le recevez '
         . 'par email, avec un code unique, et il vous suffit de le transmettre à la personne '
         . 'de votre choix. Le bénéficiaire contacte ensuite le club pour convenir d’une date.',
  ],
  [
    'q' => 'Quelle est la durée de validité d’un bon cadeau ?',
    'r' => 'Le bon cadeau est valable un an à compter de son achat. Le bénéficiaire choisit sa '
         . 'date de vol librement pendant cette période, selon les disponibilités et la météo.',
  ],
  [
    'q' => 'Que se passe-t-il en cas de mauvaise météo ?',
    'r' => 'Le vol dépend des conditions météorologiques. Si elles ne permettent pas de voler en '
         . 'sécurité, nous vous prévenons par téléphone avant votre venue et convenons ensemble '
         . 'd’une nouvelle date. Aucun vol n’est jamais maintenu si les conditions ne sont pas réunies.',
  ],
  [
    'q' => 'Y a-t-il un âge minimum ou des conditions particulières ?',
    'r' => 'Le vol découverte est ouvert à tous, y compris aux enfants accompagnés. Aucune '
         . 'condition physique particulière n’est requise pour un simple vol. Pour une formation '
         . 'au pilotage, un certificat médical est en revanche nécessaire.',
  ],
  [
    'q' => 'Où se situe l’aérodrome et comment s’y rendre ?',
    'r' => 'Le club est basé sur l’aérodrome de Saumur Terrefort, route de Marson, à 49400 Saumur '
         . '(commune de Saint-Hilaire-Saint-Florent), à 2,5 km au sud-ouest du centre de Saumur. '
         . 'Un vaste parking est à disposition sur place.',
  ],
  [
    'q' => 'Comment apprendre à piloter et adhérer au club ?',
    'r' => 'Le Saumur Air Club forme au brevet de pilote (PPL, LAPL) et propose aussi le BIA et '
         . 'l’ULM. On vole en association : cotisation annuelle, licence fédérale, puis un tarif '
         . 'horaire par avion. Le préalable à toute inscription est un vol d’initiation. '
         . 'Retrouvez les détails sur la page Tarifs & inscriptions.',
  ],
];
?>

<section class="section">
  <div class="conteneur" style="max-width:820px">
    <div class="faq">
      <?php foreach ($faq as $i => $item): ?>
        <details class="faq__item"<?= $i === 0 ? ' open' : '' ?>>
          <summary class="faq__q"><?= texte('faq.q' . ($i + 1), $item['q']) ?></summary>
          <div class="faq__r"><p><?= texte('faq.r' . ($i + 1), $item['r'], 'long') ?></p></div>
        </details>
      <?php endforeach; ?>
    </div>

    <div class="appel" style="margin-top:2.5rem">
      <div class="appel__texte">
        <p class="surtitre">Une autre question ?</p>
        <h2>Le club vous répond</h2>
        <p>Par téléphone, par email, ou sur place à l’aérodrome.</p>
      </div>
      <a class="bouton bouton--or" href="<?= e(url('contact')) ?>">Nous contacter</a>
    </div>
  </div>
</section>

<?php
/* Données structurées FAQPage : c'est ce qui permet à Google d'afficher
   les questions directement dans les résultats de recherche. */
$questions = [];
foreach ($faq as $item) {
    $questions[] = [
        '@type' => 'Question',
        'name'  => $item['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['r']],
    ];
}
$ld = [
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => $questions,
];
echo '<script type="application/ld+json">'
   . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
   . '</script>';
?>

<?php require __DIR__ . '/inc/footer.php'; ?>
