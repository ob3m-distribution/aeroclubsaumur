<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/bon-cadeau.php';
exiger_connexion();

$pdo = db();

/* Chiffres de tête */
$stats = [
    'attente'  => (int) $pdo->query("SELECT COUNT(*) FROM bons_cadeaux WHERE statut='en_attente_paiement'")->fetchColumn(),
    'payes'    => (int) $pdo->query("SELECT COUNT(*) FROM bons_cadeaux WHERE statut='paye'")->fetchColumn(),
    'utilises' => (int) $pdo->query("SELECT COUNT(*) FROM bons_cadeaux WHERE statut='utilise'")->fetchColumn(),
];
$stats['ca'] = (int) $pdo->query(
    "SELECT COALESCE(SUM(montant_cents),0) FROM bons_cadeaux WHERE statut IN ('paye','utilise')"
)->fetchColumn();

/* Bons qui expirent bientôt : le club a intérêt à relancer. */
$bientot = $pdo->query(
    "SELECT * FROM bons_cadeaux
      WHERE statut='paye' AND expire_le IS NOT NULL
        AND expire_le BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
      ORDER BY expire_le ASC LIMIT 5"
)->fetchAll();

$derniers = $pdo->query('SELECT * FROM bons_cadeaux ORDER BY cree_le DESC LIMIT 8')->fetchAll();

$titre = 'Tableau de bord';
$actif = 'accueil';
require __DIR__ . '/inc/entete.php';

?>

<div class="grille grille--4" style="margin-bottom:1.5rem">
  <div class="stat stat--ambre">
    <span class="stat__valeur"><?= $stats['attente'] ?></span>
    <span class="stat__libelle">En attente de paiement</span>
  </div>
  <div class="stat stat--vert">
    <span class="stat__valeur"><?= $stats['payes'] ?></span>
    <span class="stat__libelle">Bons payés, à utiliser</span>
  </div>
  <div class="stat stat--gris">
    <span class="stat__valeur"><?= $stats['utilises'] ?></span>
    <span class="stat__libelle">Vols effectués</span>
  </div>
  <div class="stat">
    <span class="stat__valeur"><?= e(prix($stats['ca'])) ?></span>
    <span class="stat__libelle">Encaissé au total</span>
  </div>
</div>

<?php if ($bientot): ?>
  <div class="bloc" style="border-left:3px solid var(--ambre)">
    <div class="bloc__titre">
      <h2>Bons qui expirent bientôt</h2>
      <span class="etat etat--planifie"><?= count($bientot) ?> à relancer</span>
    </div>
    <p style="color:var(--gris-700)">
      Ces bons ont été payés mais pas encore utilisés, et arrivent à échéance dans moins de deux mois.
    </p>
    <div class="tableau">
      <table>
        <thead><tr><th>Code</th><th>Acheteur</th><th>Expire le</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($bientot as $b): ?>
          <tr>
            <td class="code-bon"><?= e((string) $b['code']) ?></td>
            <td><?= e($b['acheteur_prenom'] . ' ' . $b['acheteur_nom']) ?><br>
                <span style="color:var(--gris-500);font-size:.8125rem"><?= e($b['acheteur_email']) ?></span></td>
            <td><?= e(date('d/m/Y', strtotime((string) $b['expire_le']))) ?></td>
            <td class="nombre"><a class="btn btn--contour btn--petit" href="/admin/bon.php?id=<?= (int) $b['id'] ?>">Ouvrir</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="detail">

  <div class="bloc">
    <div class="bloc__titre">
      <h2>Dernières demandes</h2>
      <?php if (peut('bons.voir')): ?>
        <a class="btn btn--contour btn--petit" href="/admin/bons.php">Tout voir</a>
      <?php endif; ?>
    </div>

    <?php if (!$derniers): ?>
      <p class="vide">Aucune demande pour l’instant.</p>
    <?php else: ?>
      <div class="tableau">
        <table>
          <thead><tr><th>Référence</th><th>Acheteur</th><th>Statut</th><th class="nombre">Date</th></tr></thead>
          <tbody>
          <?php foreach ($derniers as $b): [$lib, $cls] = STATUTS_BON[$b['statut']] ?? [$b['statut'], 'expire']; ?>
            <tr>
              <td class="code-bon">
                <?php if (peut('bons.voir')): ?>
                  <a href="/admin/bon.php?id=<?= (int) $b['id'] ?>"><?= e($b['code'] ?? $b['reference']) ?></a>
                <?php else: ?>
                  <?= e($b['code'] ?? $b['reference']) ?>
                <?php endif; ?>
              </td>
              <td><?= e($b['acheteur_prenom'] . ' ' . $b['acheteur_nom']) ?></td>
              <td><span class="etat etat--<?= e($cls) ?>"><?= e($lib) ?></span></td>
              <td class="nombre"><?= e(date('d/m/Y', strtotime((string) $b['cree_le']))) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require __DIR__ . '/inc/pied.php'; ?>
