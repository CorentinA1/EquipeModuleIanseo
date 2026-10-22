/**
 * tableau.js — tableau de bord : boutons « Supprimer » et « Annuler cette application ».
 *
 * Chaque action est confirmée avec un message qui dit ce qui est touché et ce
 * qui ne l'est pas, puis envoyée à action.php (act=delete ou act=undo), qui
 * refait tous les contrôles. La page est rechargée avec le résultat.
 */
(function () {
    'use strict';

    const C = window.EQUIPE;
    const UNDO_NOTE = '\n\nAnnulable depuis le tableau de bord (« Annuler cette application »).';

    async function send(data) {
        try {
            const r = await fetch(C.action, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.assign({ token: C.token }, data)),
            });
            return JSON.parse(await r.text());
        } catch (e) {
            return { error: 1, msg: 'Réponse inattendue du serveur.' };
        }
    }

    function show(level, msg) {
        const box = document.getElementById('eq-msg');
        box.innerHTML = '';
        const div = document.createElement('div');
        div.className = 'eq-banner ' + (level === 'ok' ? 'eq-green' : 'eq-red');
        div.textContent = msg;
        box.appendChild(div);
        window.scrollTo(0, 0);
    }

    /** Message de confirmation et données envoyées pour un bouton, ou null. */
    function request(b) {
        if (b.dataset.undo) {
            const started = C.started && b.dataset.undo === 'classe';
            return {
                text: 'Annuler la dernière application ?\n\n' + (b.dataset.undo === 'libre'
                    ? 'Les équipes libres reviennent comme avant. Rien n\'est modifié dans Ianseo.'
                    : 'La case « Équipe » des archers concernés revient comme avant, et Ianseo recalcule le classement par équipes.')
                    + (started ? '\n\nLe tir a commencé : le classement par équipes va changer.' : ''),
                data: { act: 'undo', confirm: 1 },
            };
        }
        const kind = b.dataset.del;
        if (kind === 'classe') {
            return {
                text: `Supprimer l'équipe ${b.dataset.name} du classement par équipes ?\n\n`
                    + 'La case « Équipe » de ses archers sera décochée et Ianseo recalculera le classement par équipes.\n'
                    + 'Qualifications individuelles, duels et finales ne sont pas touchés.'
                    + (C.started ? '\n\nLe tir a commencé : le classement par équipes va changer.' : '') + UNDO_NOTE,
                data: { act: 'delete', kind: 'classe', cat: b.dataset.cat, club: +b.dataset.club, confirm: 1 },
            };
        }
        if (kind === 'libre') {
            return {
                text: `Supprimer l'équipe libre ${b.dataset.name} ?\n\nRien n'est modifié dans Ianseo : seule la liste des équipes libres du module change.` + UNDO_NOTE,
                data: { act: 'delete', kind: 'libre', club: +b.dataset.club, members: JSON.parse(b.dataset.members) },
            };
        }
        if (kind === 'libre-all') {
            return {
                text: 'Supprimer TOUTES les équipes libres ?\n\nRien n\'est modifié dans Ianseo : seule la liste des équipes libres du module est vidée.' + UNDO_NOTE,
                data: { act: 'delete', kind: 'libre', all: 1 },
            };
        }
        return null;
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Message laissé par l'action précédente (après rechargement).
        try {
            const m = sessionStorage.getItem('equipe-msg');
            if (m) { sessionStorage.removeItem('equipe-msg'); show('ok', m); }
        } catch (e) { /* stockage indisponible */ }

        document.getElementById('eq').addEventListener('click', async (ev) => {
            const b = ev.target.closest('[data-del], [data-undo]');
            if (!b || b.disabled) return;
            const req = request(b);
            if (!req || !confirm(req.text)) return;
            b.disabled = true;
            const r = await send(req.data);
            if (r.error) {
                b.disabled = false;
                show('error', r.msg);
                return;
            }
            try { sessionStorage.setItem('equipe-msg', r.msg); } catch (e) { /* stockage indisponible */ }
            location.reload();
        });
    });
})();
