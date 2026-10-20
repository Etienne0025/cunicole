/*
 * Mode hors ligne du gestionnaire.
 * - File de saisies dans IndexedDB : une entrée par formulaire marqué data-hors-ligne.
 * - Envoi au serveur (POST JSON) ; chaque saisie porte un identifiant unique, donc un renvoi ne crée jamais de doublon.
 * - Service worker (/sw.js) : garde les pages consultables sans réseau.
 * L'appel demarrer() est volontairement placé en fin de fichier.
 */

const NOM_BASE = 'cunicole-hors-ligne';
const NOM_STORE = 'actions';
const DUREE_PREPARATION_MS = 12 * 60 * 60 * 1000;
const TENTATIVES_MAX = 10;

const PAGES_A_PREPARER = [
    '/gestionnaire', '/gestionnaire/taches', '/gestionnaire/elevage', '/gestionnaire/cages',
    '/gestionnaire/reproducteurs', '/gestionnaire/saillies', '/gestionnaire/lots',
    '/gestionnaire/alimentation', '/gestionnaire/ventes', '/gestionnaire/ventes/nouvelle',
    '/gestionnaire/depenses', '/gestionnaire/sante', '/gestionnaire/plus', '/gestionnaire/synchronisation',
];
const MOTIF_LIENS = /(\/gestionnaire\/(?:saillies|lots|reproducteurs)\/\d+|\/gestionnaire\/cages\/[0-9a-f-]{36})(?=["'?#])/g;

const STYLES_BANDEAU = {
    ok: 'bg-emerald-100 text-emerald-900',
    attente: 'bg-amber-100 text-amber-900',
    erreur: 'bg-red-100 text-red-800',
};

let promesseBase = null;
let synchroEnCours = null;
let reseauKo = false;
let attenteEnCache = 0;

function meta(nom) {
    return document.querySelector(`meta[name="${nom}"]`)?.content ?? null;
}

function utilisateur() {
    return meta('utilisateur-id');
}

function noeud(balise, classes, texte) {
    const element = document.createElement(balise);
    if (classes) element.className = classes;
    if (texte !== undefined) element.textContent = texte;
    return element;
}

function uuid() {
    if (crypto.randomUUID) return crypto.randomUUID();

    // Contexte non sécurisé (http sur le réseau local) : randomUUID n'existe pas
    const o = crypto.getRandomValues(new Uint8Array(16));
    o[6] = (o[6] & 0x0f) | 0x40;
    o[8] = (o[8] & 0x3f) | 0x80;
    const h = [...o].map((b) => b.toString(16).padStart(2, '0')).join('');

    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

function decouper(liste, taille) {
    const morceaux = [];
    for (let i = 0; i < liste.length; i += taille) morceaux.push(liste.slice(i, i + taille));
    return morceaux;
}

// ---------------------------------------------------------------------------
// Base locale (IndexedDB)
// ---------------------------------------------------------------------------

function base() {
    promesseBase ??= new Promise((resolve, reject) => {
        const requete = indexedDB.open(NOM_BASE, 1);
        requete.onupgradeneeded = () => requete.result.createObjectStore(NOM_STORE, { keyPath: 'uuid' });
        requete.onsuccess = () => resolve(requete.result);
        requete.onerror = () => reject(requete.error);
    });

    return promesseBase;
}

async function operation(mode, action) {
    const db = await base();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(NOM_STORE, mode);
        const requete = action(tx.objectStore(NOM_STORE));
        tx.oncomplete = () => resolve(requete ? requete.result : undefined);
        tx.onerror = () => reject(tx.error);
        tx.onabort = () => reject(tx.error);
    });
}

function lire(id) { return operation('readonly', (s) => s.get(id)); }
function ecrire(action) { return operation('readwrite', (s) => s.put(action)); }
function retirer(id) { return operation('readwrite', (s) => s.delete(id)); }

/** Saisies du compte connecté, dans l'ordre où elles ont été faites. */
async function mesActions() {
    const toutes = await operation('readonly', (s) => s.getAll());

    return toutes
        .filter((a) => a.utilisateur === utilisateur())
        .sort((a, b) => a.cree_le - b.cree_le);
}

// ---------------------------------------------------------------------------
// Formulaires
// ---------------------------------------------------------------------------

function formVersObjet(donnees) {
    const racine = {};

    for (const [cle, valeur] of donnees.entries()) {
        if (cle === '_token' || cle === '_method' || typeof valeur !== 'string') continue;

        const chemin = cle.split(/[\[\]]+/).filter(Boolean);
        let courant = racine;

        chemin.forEach((segment, i) => {
            if (i === chemin.length - 1) {
                courant[segment] = valeur;
            } else {
                courant = courant[segment] ??= {};
            }
        });
    }

    return racine;
}

/** Les clés 0, 1, 2... d'un objet deviennent un vrai tableau (lignes d'une vente). */
function versTableaux(valeur) {
    if (valeur === null || typeof valeur !== 'object') return valeur;

    const cles = Object.keys(valeur);
    const estTableau = cles.length > 0 && cles.every((c, i) => c === String(i));
    const copie = estTableau ? [] : {};

    for (const c of cles) copie[c] = versTableaux(valeur[c]);

    return copie;
}

function gererSubmit(evenement) {
    const form = evenement.target;

    if (!(form instanceof HTMLFormElement)) return;

    if (form.matches('form[action$="/logout"]')) {
        if (attenteEnCache > 0 && !confirm(`${attenteEnCache} saisie(s) faite(s) hors ligne ne sont pas encore envoyées. Elles resteront sur ce téléphone jusqu'à votre prochaine connexion. Se déconnecter quand même ?`)) {
            evenement.preventDefault();
            return;
        }
        navigator.serviceWorker?.controller?.postMessage({ type: 'vider-pages' });
        return;
    }

    if (form.hasAttribute('data-attendre-synchro')) {
        attendreSynchro(form, evenement);
        return;
    }

    if (form.dataset.horsLigne) {
        evenement.preventDefault();
        soumettre(form, evenement);
    }
}

async function soumettre(form, evenement) {
    const donnees = new FormData(form);

    if (evenement.submitter?.name) donnees.append(evenement.submitter.name, evenement.submitter.value);

    const complement = form.dataset.libelleChamp ? form.elements[form.dataset.libelleChamp]?.value : '';

    const action = {
        uuid: uuid(),
        utilisateur: utilisateur(),
        type: form.dataset.horsLigne,
        cle: form.dataset.cle ?? null,
        libelle: [form.dataset.libelle ?? form.dataset.horsLigne, complement].filter(Boolean).join(' : '),
        payload: versTableaux(formVersObjet(donnees)),
        effectue_le: new Date().toISOString(),
        cree_le: Date.now(),
        statut: 'en_attente',
        direct: true,
        tentatives: 0,
        message: null,
    };

    const boutons = [...form.querySelectorAll('button')];
    boutons.forEach((b) => { b.disabled = true; });

    try {
        await ecrire(action);
    } catch (erreur) {
        // Stockage local impossible : on laisse le navigateur envoyer le formulaire de façon classique
        form.removeAttribute('data-hors-ligne');
        form.submit();
        return;
    }

    let resultats = await synchroniser();

    if (!resultats[action.uuid] && !reseauKo) resultats = await synchroniser();

    const resultat = resultats[action.uuid];

    if (resultat?.statut === 'appliquee') {
        sessionStorage.setItem('bandeau-hors-ligne', JSON.stringify({ type: 'ok', texte: resultat.message }));
        window.location.href = resultat.url || form.dataset.retour || window.location.href;
        return;
    }

    if (resultat?.statut === 'refusee') {
        // Refus immédiat : l'utilisateur voit pourquoi, corrige et renvoie. Rien n'est gardé.
        await retirer(action.uuid);
        boutons.forEach((b) => { b.disabled = false; });
        form.dispatchEvent(new CustomEvent('envoi-termine'));
        bandeau('erreur', resultat.message);
        rafraichir();
        return;
    }

    // Pas de réseau (ou erreur du serveur) : la saisie reste sur le téléphone
    await ecrire({ ...action, direct: false });
    apresMiseEnAttente(form, boutons, action);
}

function apresMiseEnAttente(form, boutons, action) {
    const message = `Enregistré sur ce téléphone : « ${action.libelle} ». Il sera envoyé automatiquement dès le retour du réseau.`;
    const mode = form.dataset.apres ?? 'reinitialiser';

    if (mode === 'recharger') {
        sessionStorage.setItem('bandeau-hors-ligne', JSON.stringify({ type: 'attente', texte: message }));
        window.location.reload();
        return;
    }

    if (mode === 'masquer') {
        masquerFormulaire(form);
    } else {
        form.reset();
        boutons.forEach((b) => { b.disabled = false; });
        form.dispatchEvent(new CustomEvent('envoi-termine'));
    }

    bandeau('attente', message);
    rafraichir();
}

function masquerFormulaire(form) {
    form.replaceWith(noeud('p', 'rounded bg-amber-50 p-2 text-sm text-amber-800', "⏳ En attente d'envoi (enregistré sur ce téléphone)"));
}

/** Après un rechargement hors ligne : les formulaires déjà saisis restent masqués. */
async function marquerFormulaires() {
    const attente = (await mesActions()).filter((a) => a.statut === 'en_attente' && a.cle);

    for (const action of attente) {
        const form = document.querySelector(`form[data-apres="masquer"][data-cle="${CSS.escape(action.cle)}"]`);
        if (form) masquerFormulaire(form);
    }
}

async function attendreSynchro(form, evenement) {
    if (form.dataset.pret) return;

    evenement.preventDefault();
    await synchroniser();

    const restantes = (await mesActions()).filter((a) => a.statut === 'en_attente').length;

    if (restantes > 0) {
        bandeau('erreur', `${restantes} saisie(s) faite(s) hors ligne ne sont pas encore envoyées. Rétablissez la connexion : une fois envoyées, vous pourrez clôturer la journée.`);
        return;
    }

    form.dataset.pret = '1';
    form.submit();
}

/** Page de clôture : on envoie d'abord les saisies en attente, sinon des tâches déjà faites apparaîtraient à justifier. */
async function verifierAvantCloture() {
    const avant = (await mesActions()).filter((a) => a.statut === 'en_attente').length;

    if (avant === 0) return;

    bandeau('attente', `${avant} saisie(s) faite(s) hors ligne en attente d'envoi : envoi en cours...`);

    if (navigator.onLine) {
        await synchroniser();

        const restantes = (await mesActions()).filter((a) => a.statut === 'en_attente').length;

        if (restantes === 0) {
            sessionStorage.setItem('bandeau-hors-ligne', JSON.stringify({ type: 'ok', texte: 'Saisies hors ligne envoyées. Vérifiez les tâches avant de clôturer la journée.' }));
            window.location.reload();
            return;
        }
    }

    bandeau('erreur', 'Des saisies faites hors ligne ne sont pas encore envoyées. Attendez le retour du réseau avant de clôturer la journée.');
}

// ---------------------------------------------------------------------------
// Synchronisation
// ---------------------------------------------------------------------------

function synchroniser() {
    if (synchroEnCours) return synchroEnCours;

    synchroEnCours = envoyerLaFile().finally(() => {
        synchroEnCours = null;
        rafraichir();
    });

    return synchroEnCours;
}

async function envoyerLaFile() {
    const resultats = {};

    try {
        const attente = (await mesActions()).filter((a) => a.statut === 'en_attente');

        if (attente.length === 0) {
            reseauKo = false;
            return resultats;
        }

        // Vérifie la session et récupère un jeton CSRF à jour (une page ancienne peut en contenir un périmé)
        const etat = await fetch(meta('url-etat-synchronisation'), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        });

        if (etat.status === 401 || etat.status === 419) {
            sessionExpiree();
            return resultats;
        }
        if (!etat.ok) throw new Error('etat');

        const { csrf } = await etat.json();

        for (const lot of decouper(attente, 20)) {
            const reponse = await fetch(meta('url-synchronisation'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    actions: lot.map((a) => ({
                        uuid: a.uuid,
                        type: a.type,
                        libelle: a.libelle,
                        payload: a.payload,
                        effectue_le: a.effectue_le,
                        direct: a.direct === true,
                    })),
                }),
            });

            if (reponse.status === 401 || reponse.status === 419) {
                sessionExpiree();
                break;
            }
            if (!reponse.ok) throw new Error('envoi');

            const donnees = await reponse.json();

            for (const resultat of donnees.resultats) {
                resultats[resultat.uuid] = resultat;
                await enregistrerResultat(resultat);
            }
        }

        reseauKo = false;
    } catch (erreur) {
        reseauKo = true;
    }

    return resultats;
}

async function enregistrerResultat(resultat) {
    const action = await lire(resultat.uuid);

    if (!action) return;

    if (resultat.statut === 'appliquee') {
        await retirer(resultat.uuid);
    } else if (resultat.statut === 'refusee') {
        await ecrire({ ...action, statut: 'refusee', message: resultat.message });
    } else {
        const tentatives = (action.tentatives ?? 0) + 1;

        await ecrire(tentatives >= TENTATIVES_MAX
            ? { ...action, tentatives, statut: 'refusee', message: 'Erreur technique persistante : prévenez le propriétaire.' }
            : { ...action, tentatives, message: resultat.message });
    }
}

/** Envoi automatique (retour du réseau, minuterie) avec un message récapitulatif. */
async function synchroniserEnArriere() {
    const resultats = Object.values(await synchroniser());

    if (resultats.length === 0) return;

    const envoyees = resultats.filter((r) => r.statut === 'appliquee').length;
    const refusees = resultats.filter((r) => r.statut === 'refusee').length;

    if (refusees > 0) {
        bandeau('erreur', `${refusees} saisie(s) faite(s) hors ligne ont été refusées par le serveur. Ouvrez la page Synchronisation pour voir pourquoi.${envoyees ? ` ${envoyees} autre(s) envoyée(s).` : ''}`);
    } else if (envoyees > 0) {
        bandeau('ok', `${envoyees} saisie(s) faite(s) hors ligne envoyée(s) au serveur. Actualisez la page pour voir les données à jour.`);
    }
}

function sessionExpiree() {
    bandeau('erreur', "Votre session n'est plus valide (session expirée ou compte désactivé). Reconnectez-vous : vos saisies enregistrées sur ce téléphone seront envoyées ensuite.", { href: '/login', texte: 'Se reconnecter' });
}

// ---------------------------------------------------------------------------
// Affichage
// ---------------------------------------------------------------------------

function bandeau(type, texte, lien) {
    const zone = document.getElementById('bandeau-sync');

    if (!zone) return;

    zone.className = `mb-4 rounded p-3 text-sm ${STYLES_BANDEAU[type] ?? STYLES_BANDEAU.attente}`;
    zone.textContent = texte;

    if (lien) {
        const ancre = noeud('a', 'ml-2 underline', lien.texte);
        ancre.href = lien.href;
        zone.append(ancre);
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function afficherBandeauStocke() {
    const stocke = sessionStorage.getItem('bandeau-hors-ligne');

    if (!stocke) return;

    sessionStorage.removeItem('bandeau-hors-ligne');

    try {
        const { type, texte } = JSON.parse(stocke);
        bandeau(type, texte);
    } catch (erreur) { /* message illisible : ignoré */ }
}

async function rafraichir() {
    const actions = await mesActions();
    const attente = actions.filter((a) => a.statut === 'en_attente').length;
    const refusees = actions.filter((a) => a.statut === 'refusee').length;
    attenteEnCache = attente;

    const enLigne = navigator.onLine && !reseauKo;
    const statut = document.getElementById('statut-connexion');

    if (statut) {
        statut.textContent = enLigne ? 'En ligne' : 'Hors ligne';
        statut.className = enLigne
            ? 'rounded-full bg-white/20 px-2 py-0.5'
            : 'rounded-full bg-amber-400 px-2 py-0.5 font-semibold text-gray-900';
    }

    const pastille = document.getElementById('compteur-sync');

    if (pastille) {
        const morceaux = [];
        if (attente) morceaux.push(`${attente} en attente`);
        if (refusees) morceaux.push(`${refusees} refusée(s)`);

        pastille.textContent = morceaux.join(' · ');
        pastille.className = morceaux.length === 0
            ? 'hidden'
            : (refusees > 0
                ? 'rounded-full bg-red-500 px-2 py-0.5 text-xs font-semibold text-white'
                : 'rounded-full bg-amber-400 px-2 py-0.5 text-xs font-semibold text-gray-900');
    }

    document.dispatchEvent(new CustomEvent('hors-ligne:maj'));
}

async function rendreSynchronisation() {
    const zone = document.getElementById('liste-synchronisation');

    if (!zone) return;

    const actions = await mesActions();
    zone.replaceChildren();

    if (actions.length === 0) {
        zone.append(noeud('p', 'text-sm text-gray-500', 'Aucune saisie en attente : tout est à jour.'));
        return;
    }

    for (const action of actions) {
        const refusee = action.statut === 'refusee';
        const carte = noeud('div', `rounded border p-3 text-sm ${refusee ? 'border-red-300 bg-red-50' : 'border-amber-300 bg-amber-50'}`);

        carte.append(noeud('div', 'font-medium', action.libelle));
        carte.append(noeud('div', 'text-xs text-gray-500', `Saisie du ${new Date(action.effectue_le).toLocaleString('fr-FR')}`));

        if (refusee) {
            carte.append(noeud('div', 'mt-1 text-red-800', `Refusée : ${action.message}`));

            const details = noeud('details', 'mt-1 text-xs text-gray-600');
            details.append(noeud('summary', 'cursor-pointer', 'Voir les données saisies (pour les ressaisir)'));
            details.append(noeud('pre', 'mt-1 whitespace-pre-wrap break-all', JSON.stringify(action.payload, null, 2)));
            carte.append(details);
        } else {
            carte.append(noeud('div', 'mt-1 text-amber-800', `En attente d'envoi${action.message ? ` (${action.message})` : ''}`));
        }

        const bouton = noeud('button', 'mt-2 text-xs text-red-600 underline', refusee ? 'Supprimer de la liste' : 'Annuler cette saisie');
        bouton.type = 'button';
        bouton.addEventListener('click', async () => {
            if (!refusee && !confirm('Annuler cette saisie ? Elle ne sera jamais envoyée.')) return;

            await retirer(action.uuid);
            rafraichir();
        });

        carte.append(bouton);
        zone.append(carte);
    }
}

function brancherPageSynchronisation() {
    document.addEventListener('hors-ligne:maj', rendreSynchronisation);

    const envoyer = document.getElementById('bouton-synchroniser');

    envoyer?.addEventListener('click', async () => {
        envoyer.disabled = true;

        if ((await mesActions()).filter((a) => a.statut === 'en_attente').length === 0) {
            bandeau('ok', 'Tout est déjà envoyé.');
        } else {
            await synchroniserEnArriere();
            if (reseauKo) bandeau('attente', 'Le serveur est injoignable pour le moment. Les saisies restent sur le téléphone.');
        }

        envoyer.disabled = false;
    });

    const preparer_ = document.getElementById('bouton-preparer');
    const etat = document.getElementById('etat-preparation');

    const derniere = Number(localStorage.getItem('preparation-hors-ligne') || 0);
    if (etat && derniere) etat.textContent = `Dernière préparation : ${new Date(derniere).toLocaleString('fr-FR')}.`;

    preparer_?.addEventListener('click', async () => {
        preparer_.disabled = true;
        etat.textContent = 'Téléchargement en cours...';

        try {
            const total = await preparer((n) => { etat.textContent = `Téléchargement en cours... ${n} page(s)`; });
            etat.textContent = `${total} page(s) disponibles hors ligne.`;
        } catch (erreur) {
            etat.textContent = 'Impossible pour le moment : vérifiez votre connexion (et que le site est en HTTPS) puis réessayez.';
        }

        preparer_.disabled = false;
    });
}

// ---------------------------------------------------------------------------
// Pages disponibles hors ligne
// ---------------------------------------------------------------------------

async function preparer(progression = () => {}) {
    if (!('serviceWorker' in navigator) || !navigator.onLine) throw new Error('indisponible');

    await Promise.race([
        navigator.serviceWorker.ready,
        new Promise((_, rejeter) => setTimeout(() => rejeter(new Error('sw')), 8000)),
    ]);

    const entetes = { 'X-Preparation': '1', Accept: 'text/html' };
    const liens = new Set();
    let faites = 0;

    // Fichiers CSS et JS de la page courante, pour que les pages soient mises en forme hors ligne
    const fichiers = [...document.querySelectorAll('link[href*="/build/"], script[src*="/build/"]')].map((n) => n.href || n.src);
    await Promise.allSettled(fichiers.map((u) => fetch(u)));

    const recuperer = async (chemin) => {
        try {
            const reponse = await fetch(chemin, { headers: entetes, credentials: 'same-origin' });

            if (reponse.ok && !reponse.redirected) {
                const html = await reponse.text();
                for (const m of html.matchAll(MOTIF_LIENS)) liens.add(m[1]);
                faites++;
            }
        } catch (erreur) { /* page ignorée */ }

        progression(faites);
    };

    for (const lot of decouper(PAGES_A_PREPARER, 4)) await Promise.all(lot.map(recuperer));
    // Fiches des animaux, lots, saillies et cages trouvées dans les listes
    for (const lot of decouper([...liens].slice(0, 60), 4)) await Promise.all(lot.map(recuperer));

    localStorage.setItem('preparation-hors-ligne', String(Date.now()));

    return faites;
}

function planifierPreparation() {
    const derniere = Number(localStorage.getItem('preparation-hors-ligne') || 0);

    if (navigator.onLine && Date.now() - derniere > DUREE_PREPARATION_MS) {
        setTimeout(() => preparer().catch(() => {}), 4000);
    }
}

// ---------------------------------------------------------------------------
// Démarrage
// ---------------------------------------------------------------------------

function demarrer() {
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }

    document.addEventListener('submit', gererSubmit);

    window.addEventListener('online', () => {
        reseauKo = false;
        rafraichir();
        synchroniserEnArriere();
    });
    window.addEventListener('offline', () => {
        rafraichir();
        bandeau('attente', 'Vous êtes hors ligne : vos saisies seront enregistrées sur ce téléphone.');
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && navigator.onLine) synchroniserEnArriere();
    });
    setInterval(() => { if (navigator.onLine && attenteEnCache > 0) synchroniserEnArriere(); }, 45000);

    afficherBandeauStocke();
    marquerFormulaires();
    rafraichir();
    brancherPageSynchronisation();
    rendreSynchronisation();

    if (document.querySelector('form[data-attendre-synchro]')) {
        verifierAvantCloture();
    } else if (navigator.onLine) {
        synchroniserEnArriere();
    }

    planifierPreparation();
}

if (document.body?.dataset.espace === 'gestionnaire' && 'indexedDB' in window) {
    demarrer();
}