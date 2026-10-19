/**
 * equipes.js — page « Équipes créées » : boutons « Supprimer ».
 *
 * Chaque suppression est confirmée avec un message qui dit ce qui est touché
 * et ce qui ne l'est pas, puis envoyée à action.php (act=delete), qui refait
 * tous les contrôles. La page est rechargée avec le résultat.
 */
(function () {
    'use strict';

    const C = window.EQUIPE;

    async function send(data) {
        try {
            const r = await fetch(C.action, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.assign({ act: 'delete', token: C.token }, data)),
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

    document.addEventListener('DOMContentLoaded', () => {
        // Message laissé par la suppression précédente (après rechargement).
        try {
            const m = sessionStorage.getItem('equipe-msg');
            if (m) { sessionStorage.removeItem('equipe-msg'); show('ok', m); }
        } catch (e) { /* stockage indisponible */ }

        document.getElementById('eq').addEventListener('click', async (ev) => {
            const b = ev.target.closest('[data-del]');
            if (!b || b.disabled) return;
            const kind = b.dataset.del;
            let text;
            let data;
            if (kind === 'classe') {
                text = `Supprimer l'équipe ${b.dataset.name} du classement par équipes ?\n\n`
                    + 'La case « Équipe » de ses archers sera décochée et Ianseo recalculera le classement par équipes.\n'
                    + 'Qualifications individuelles, duels et finales ne sont pas touchés.'
                    + (C.started ? '\n\nLe tir a commencé : le classement par équipes va changer.' : '')
                    + '\n\nAnnulable depuis l\'assistant (« Annuler cette application »).';
                data = { kind: 'classe', cat: b.dataset.cat, club: +b.dataset.club, confirm: 1 };
            } else if (kind === 'libre') {
                text = `Supprimer l'équipe libre ${b.dataset.name} ?\n\nRien n'est modifié dans Ianseo : seule la liste des équipes libres du module change.`
                    + '\n\nAnnulable depuis l\'assistant (« Annuler cette application »).';
                data = { kind: 'libre', club: +b.dataset.club, members: JSON.parse(b.dataset.members) };
            } else {
                text = 'Supprimer TOUTES les équipes libres ?\n\nRien n\'est modifié dans Ianseo : seule la liste des équipes libres du module est vidée.'
                    + '\n\nAnnulable depuis l\'assistant (« Annuler cette application »).';
                data = { kind: 'libre', all: 1 };
            }
            if (!confirm(text)) return;
            b.disabled = true;
            const r = await send(data);
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
