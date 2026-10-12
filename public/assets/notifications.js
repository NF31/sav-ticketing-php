// Notifications en direct : interroge /notifications/etat toutes les 15 s
// pour mettre a jour le badge et le titre de l'onglet, et affiche chaque
// nouvelle notification dans un message en bas de l'ecran.
//
// Interrogation periodique plutot qu'une connexion ouverte en permanence
// (Server-Sent Events, WebSocket) : en PHP classique, une connexion
// ouverte occupe un processus PHP tant que l'onglet est ouvert, et
// quelques onglets suffiraient a bloquer le serveur. Ici chaque requete
// dure quelques millisecondes.
(() => {
  'use strict';

  const INTERVALLE_MS = 15000;
  const DUREE_MESSAGE_MS = 8000;

  // Lien de la barre laterale : absent si personne n'est connecte.
  const lien = document.querySelector('.sidebar__link[href="/notifications"]');
  if (!lien) {
    return;
  }

  const titreBase = document.title.replace(/^\(\d+\+?\) /, '');
  let derniereId = null;
  let minuterie = null;
  let sessionExpiree = false;
  let zoneMessages = null;

  function majBadge(nonLues) {
    let badge = lien.querySelector('.sidebar__badge');

    if (nonLues === 0) {
      badge?.remove();
    } else {
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'sidebar__badge';
        lien.append(badge);
      }
      // textContent : jamais de HTML construit a partir de la reponse.
      const cache = document.createElement('span');
      cache.className = 'visually-hidden';
      cache.textContent = nonLues > 1 ? ' non lues' : ' non lue';
      badge.replaceChildren(nonLues > 99 ? '99+' : String(nonLues), cache);
    }

    document.title = nonLues > 0 ? `(${nonLues > 99 ? '99+' : nonLues}) ${titreBase}` : titreBase;
  }

  function afficherMessage(notification) {
    if (!zoneMessages) {
      // aria-live : les lecteurs d'ecran annoncent le message a son arrivee.
      zoneMessages = document.createElement('div');
      zoneMessages.className = 'toasts';
      zoneMessages.setAttribute('aria-live', 'polite');
      document.body.append(zoneMessages);
    }

    const message = document.createElement('div');
    message.className = 'toast';

    const lienTicket = document.createElement('a');
    lienTicket.className = 'toast__lien';
    lienTicket.href = notification.url;
    lienTicket.textContent = notification.message;

    const fermer = document.createElement('button');
    fermer.type = 'button';
    fermer.className = 'toast__fermer';
    fermer.setAttribute('aria-label', 'Fermer');
    fermer.textContent = '×';
    fermer.addEventListener('click', () => message.remove());

    message.append(lienTicket, fermer);
    zoneMessages.append(message);
    setTimeout(() => message.remove(), DUREE_MESSAGE_MS);
  }

  async function interroger() {
    const url = '/notifications/etat' + (derniereId !== null ? `?depuis=${derniereId}` : '');

    try {
      // redirect: 'manual' : session expiree = redirection vers /login,
      // inutile de la suivre ni de continuer a interroger.
      const reponse = await fetch(url, { headers: { Accept: 'application/json' }, redirect: 'manual', cache: 'no-store' });

      if (reponse.type === 'opaqueredirect') {
        sessionExpiree = true;
        arreter();
        return;
      }
      if (!reponse.ok) {
        return;
      }

      const etat = await reponse.json();
      // Les plus anciennes d'abord, pour que la plus recente soit en bas.
      etat.nouvelles.slice().reverse().forEach(afficherMessage);
      derniereId = etat.derniereId;
      majBadge(etat.nonLues);
    } catch {
      // Reseau coupe ou serveur arrete : nouvel essai au prochain tour.
    }
  }

  function demarrer() {
    if (minuterie === null && !sessionExpiree) {
      minuterie = setInterval(interroger, INTERVALLE_MS);
    }
  }

  function arreter() {
    clearInterval(minuterie);
    minuterie = null;
  }

  // Onglet en arriere-plan : aucune requete. Au retour, verification
  // immediate puis reprise du rythme normal.
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      arreter();
    } else if (!sessionExpiree) {
      interroger();
      demarrer();
    }
  });

  // Premier appel : fixe le point de depart (les notifications deja
  // presentes sont dans le badge, pas affichees en message).
  interroger();
  if (!document.hidden) {
    demarrer();
  }
})();
