/**
 * assistant.js — « Composer des équipes » : Mode, Catégories, Composition, Vérifier et appliquer, puis écran de fin.
 *
 * État dans S, rendu complet de l'étape à chaque changement, actions par
 * délégation (attribut data-act). Le serveur (action.php) refait tous les
 * contrôles : ce fichier ne fait que préparer et afficher. Impressions,
 * annulation, contrôles et journal sont sur le tableau de bord et la page
 * Contrôles, pas ici.
 *
 * Équipe affichée : { sec (indice de section), club, n (numéro local), members }.
 * Mode classe : une équipe au plus par club et par section ; mode libre : plusieurs.
 * Sélection (S.sel) : ensemble d'archers d'un même club et d'une même section ;
 * Ctrl (ou Cmd) + clic en ajoute ou en retire un, puis un clic sur une case les déplace tous.
 */
(function () {
    'use strict';

    const C = window.EQUIPE;
    const STEPS = ['Mode', 'Catégories', 'Composition', 'Vérifier et appliquer'];
    const FIN = STEPS.length + 1;   // écran de fin, après l'application
    const MODES = { classe: 'Par classe et division', libre: 'Libre' };
    const S = {
        init: null, step: 1, msg: null, mode: 'classe',
        crit: { divs: [], classes: [] }, settings: { size_min: 3, size_max: 4 },
        sections: [], teams: [], archers: {}, sel: new Set(), filter: '', nextN: 1,
        plan: null, confirm: false, busy: false, done: null,
    };

    const $app = () => document.getElementById('eq-app');
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    async function api(act, data) {
        try {
            const r = await fetch(C.action, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.assign({ act, token: C.token }, data || {})),
            });
            const txt = await r.text();
            try { return JSON.parse(txt); } catch (e) {
                return { error: 1, msg: 'Réponse inattendue du serveur : ' + txt.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').slice(0, 300) };
            }
        } catch (e) {
            return { error: 1, msg: 'Serveur injoignable : ' + e.message };
        }
    }

    const params = () => ({ mode: S.mode, divs: S.crit.divs, classes: S.crit.classes, settings: S.settings });
    const readOnly = () => !S.init.canWrite || (S.mode === 'classe' && S.init.state.locked);

    // ── Rendu ────────────────────────────────────────────────────────────────

    function render() {
        let h = '';
        if (S.msg) h += `<div class="eq-banner eq-${S.msg[0]}">${esc(S.msg[1])}</div>`;
        h += '<ol class="eq-steps" aria-label="Étapes">' + STEPS.map((l, i) =>
            `<li class="${i + 1 === S.step ? 'cur' : (i + 1 < S.step ? 'done' : '')}"${i + 1 === S.step ? ' aria-current="step"' : ''}>${i + 1}. ${esc(l)}</li>`).join('') + '</ol>';
        if (S.step > 1 && S.step < FIN) h += modeLine();
        h += [null, step1, step2, step3, step4, stepFin][S.step]();
        $app().innerHTML = h;
        if (S.step === 3) applyFilter();
    }

    /** Rappel du mode (et des critères à partir de la composition), avec retour pour les changer. */
    function modeLine() {
        let h = `<p class="eq-mode">Mode : <b>${esc(MODES[S.mode])}</b> <button class="eq-link" data-act="to1">changer</button>`;
        if (S.step > 2) {
            h += ` · ${esc(S.crit.divs.join(', '))} · ${esc(S.crit.classes.join(', '))} · ${S.settings.size_min} à ${S.settings.size_max} archers
                <button class="eq-link" data-act="to2">changer</button>`;
        }
        return h + '</p>';
    }

    function go(step) {
        S.step = step;
        S.msg = null;
        render();
        window.scrollTo(0, 0);
    }

    const alertsHtml = (alerts) => (alerts || []).map(([lvl, m]) =>
        `<div class="eq-banner ${{ error: 'eq-red', warn: 'eq-orange' }[lvl] || 'eq-info'}">${esc(m)}</div>`).join('');

    function nativePrintUrl() {
        const q = S.crit.divs.map((d) => 'Divisions[]=' + encodeURIComponent(d))
            .concat(S.crit.classes.map((c) => 'Classes[]=' + encodeURIComponent(c)));
        return C.print + (q.length ? '?' + q.join('&') : '');
    }

    // ── Étape 1 : mode ───────────────────────────────────────────────────────

    function step1() {
        const card = (k, title, text, ex, rank) => `<button type="button" class="eq-choice${S.mode === k ? ' cur' : ''}" data-act="pick-mode" data-mode="${k}">
            <span class="t">${title}</span><span>${text}</span><span class="ex">${ex}</span><span class="eq-muted">${rank}</span>
            <span class="go">Choisir ce mode</span></button>`;
        return `<p class="eq-lead">Quel type d'équipes voulez-vous composer ?</p><div class="eq-choices">`
            + card('classe', 'Par classe et division',
                'Une équipe = des archers d\'un même club et d\'une même catégorie (division + classe). Le module coche la case « Équipe » des archers retenus.',
                'Exemple : 3 archers S1H en arc classique, du même club', 'Classement : celui d\'Ianseo (classement par équipes)')
            + card('libre', 'Libre',
                'Des archers d\'un même club et d\'une même arme, quelle que soit leur classe, s\'ils tirent à la même distance sur le même blason.',
                'Exemple : un S1H, un S3H et un U21H en arc classique', 'Classement : fait par le module (Hommes, Femmes, Mixte) ; Ianseo n\'est pas modifié')
            + `</div><div class="eq-actions"><a class="eq-btn ghost" href="${esc(C.home)}">← Retour au tableau de bord</a></div>`;
    }

    // ── Étape 2 : catégories ─────────────────────────────────────────────────

    const catCount = (div, cls) => S.init.counts[div + cls] || 0;

    function step2() {
        const I = S.init;
        const box = (field, id, label, checked, n) =>
            `<label class="eq-check${checked ? ' on' : ''}"><input type="checkbox" data-field="${field}" value="${esc(id)}" ${checked ? 'checked' : ''}> ${esc(label)}${n !== undefined ? ` <span class="b">${n}</span>` : ''}</label>`;
        let h = `<div class="eq-layout"><div class="eq-main">
            <div class="eq-card"><h3>Armes (divisions)</h3>
            ${I.divisions.map((d) => box('div', d.id, `${d.id} — ${d.name}`, S.crit.divs.includes(d.id),
                I.classes.reduce((n, c) => n + catCount(d.id, c.id), 0))).join('')}</div>
            <div class="eq-card"><h3>Classes</h3>
            <div class="eq-actions" style="margin:0 0 8px"><button class="ghost small" data-act="cls-sex" data-sex="1">Toutes les femmes</button>
            <button class="ghost small" data-act="cls-sex" data-sex="0">Tous les hommes</button>
            <button class="ghost small" data-act="cls-sex" data-sex="all">Toutes</button>
            <button class="ghost small" data-act="cls-sex" data-sex="none">Aucune</button></div>`;
        for (const [sex, label] of [[1, 'Femmes'], [0, 'Hommes'], [-1, 'Autres']]) {
            const list = I.classes.filter((c) => c.sex === sex);
            if (!list.length) continue;
            h += `<div class="eq-grp"><b>${label}</b> ${list.map((c) => box('cls', c.id, c.id, S.crit.classes.includes(c.id),
                S.crit.divs.reduce((n, d) => n + catCount(d, c.id), 0))).join('')}</div>`;
        }
        h += `<p class="eq-muted">Le chiffre : archers inscrits dans les armes cochées.</p></div>
            <div class="eq-card"><h3>Archers par équipe</h3>
            de <input type="number" min="3" max="8" data-field="s-min" value="${S.settings.size_min}" aria-label="Minimum">
            à <input type="number" min="3" max="8" data-field="s-max" value="${S.settings.size_max}" aria-label="Maximum">
            <span class="eq-muted">Au-delà de 3 : remplaçants. Les 3 meilleurs scores comptent.</span></div></div>`;

        // Votre sélection : archers et catégories retenus par les cases cochées.
        const cats = [];
        for (const d of S.crit.divs) for (const c of S.crit.classes) if (catCount(d, c)) cats.push([d + c, catCount(d, c)]);
        const total = cats.reduce((n, x) => n + x[1], 0);
        h += `<aside class="eq-card eq-side" aria-label="Votre sélection"><h3>Votre sélection</h3>
            <div class="eq-big">${total} archer${total > 1 ? 's' : ''}</div>
            <p class="eq-muted">dans ${cats.length} catégorie${cats.length > 1 ? 's' : ''}</p>
            ${cats.length ? `<dl>${cats.map(([k, n]) => `<dt>${esc(k)}</dt><dd>${n}</dd>`).join('')}</dl>` : ''}</aside></div>`;
        return h + `<div class="eq-actions"><button class="ghost" data-act="to1">← Mode</button><span class="eq-grow"></span>
            <button data-act="load" ${total ? '' : 'disabled'}>Composer les équipes →</button></div>`;
    }

    // ── Étape 3 : composition ────────────────────────────────────────────────

    function teamChecks(members) {
        const n = members.length;
        if (n < 3) return ['moins de 3 archers : équipe non classée'];
        if (n < S.settings.size_min) return [`minimum ${S.settings.size_min}`];
        if (n > S.settings.size_max) return [`maximum ${S.settings.size_max}`];
        return [];
    }

    function teamScore(members) {
        const s = members.map((id) => S.archers[id]).filter((a) => a.score > 0).map((a) => a.score).sort((a, b) => b - a);
        return s.length >= 3 ? s[0] + s[1] + s[2] : null;
    }

    function chip(a) {
        return `<div class="chip ${S.sel.has(a.id) ? 'sel' : ''}" draggable="true" data-act="chip" data-id="${a.id}">
            <span class="sx-${a.sex}">${a.sex === 1 ? '♀' : '♂'}</span> ${esc(a.name)}
            ${S.mode === 'libre' ? `<span class="b">${esc(a.cls)}</span>` : ''}${S.init.state.started ? ` <b class="sc">${a.score}</b>` : ''}</div>`;
    }

    /** Classement où ira une équipe libre : Hommes, Femmes ou Mixte (même règle que le serveur). */
    function teamSex(members) {
        const sexes = new Set(members.map((id) => S.archers[id].sex));
        if (!sexes.size) return '';
        return sexes.size > 1 ? 'Mixte' : (sexes.has(1) ? 'Femmes' : 'Hommes');
    }

    function teamBox(t, si, club, target, ro) {
        const w = teamChecks(t.members);
        const sc = S.init.state.started ? teamScore(t.members) : null;
        const sex = S.mode === 'libre' ? teamSex(t.members) : '';
        return `<div class="eq-box team ${w.length ? 'bad' : 'ok'} ${target ? 'target' : ''}" data-drop="${si}|${club}|${t.n}" data-act="box">
            <div class="t">Équipe${S.mode === 'libre' ? ' ' + t.label : ''} <span class="b">${t.members.length}</span>${sc !== null ? ` <span class="b blue">${sc}</span>` : ''}
            ${sex ? `<span class="b ${sex === 'Mixte' ? 'orange' : ''}" title="Classement où ira l'équipe">${sex}</span>` : ''}
            ${S.mode === 'libre' ? `<button class="ghost small" data-act="del-team" data-n="${t.n}" title="Supprimer l'équipe" ${ro ? 'disabled' : ''}>×</button>` : ''}</div>
            ${t.members.map((id) => chip(S.archers[id])).join('') || '<span class="eq-muted">Déposez des archers ici</span>'}
            ${w.length ? `<div class="w">⚠ ${esc(w.join(' · '))}</div>` : ''}</div>`;
    }

    function step3() {
        const ro = readOnly();
        let h = `<div class="eq-card"><div class="eq-tools">
            <button class="ghost" data-act="propose" ${ro ? 'disabled' : ''}>Proposition automatique</button>
            <button class="ghost" data-act="clear" ${ro ? 'disabled' : ''}>Tout retirer</button>
            <input type="search" data-field="filter" placeholder="Filtrer : archer ou club" aria-label="Filtrer" value="${esc(S.filter)}">
            <details class="eq-how"${S.how ? ' open' : ''}><summary data-act="how">Comment faire ?</summary><ul>
            <li>Glissez un archer dans une équipe, ou touchez-le puis touchez la case.</li>
            <li><b>Ctrl + clic</b> (Cmd sur Mac) : plusieurs archers du même club, déplacés ensemble ; Échap : tout désélectionner.</li>
            <li>${S.mode === 'classe' ? 'Au départ : les archers cochés « Équipe » dans Ianseo (moins de 3 dans le club : non retenus).' : 'Au départ : les équipes libres déjà enregistrées.'}</li>
            <li>Proposition automatique : ${S.init.state.started ? 'par score' : 'par ordre d\'inscription'}${S.mode === 'libre' ? ', hommes et femmes séparés. Une équipe qui mêle hommes et femmes va dans le classement « Mixte »' : ''}.</li>
            </ul></details></div>
            ${ro ? '<div class="eq-banner eq-red">Lecture seule (droits, verrou ou garde-fou).</div>' : ''}</div>`;
        if (!S.sections.length) h += '<div class="eq-card eq-muted">Aucun archer dans ces catégories.</div>';
        S.sections.forEach((s, si) => {
            const nTeams = S.teams.filter((t) => t.sec === si && t.members.length >= 3).length;
            h += `<section class="eq-cat"><h3>${esc(s.title)}${s.sub ? ` <span class="b">${esc(s.sub)}</span>` : ''}
                <span class="eq-muted">${s.clubs.length} club(s), ${nTeams} équipe(s)</span></h3>
                ${s.known ? '' : '<div class="eq-banner eq-orange">Distance ou blason inconnu pour ces archers : vérifiez la configuration dans Ianseo.</div>'}`;
            for (const club of s.clubs) {
                const teams = S.teams.filter((t) => t.sec === si && t.club === club.id).sort((a, b) => a.n - b.n);
                const placed = new Set(teams.flatMap((t) => t.members));
                const free = club.archers.filter((a) => !placed.has(a.id));
                const sel = S.sel.size ? S.archers[S.sel.values().next().value] : null;
                const target = !!sel && sel.sec === si && sel.club === club.id;
                let boxes = teams.map((t, i) => teamBox(Object.assign(t, { label: i + 1 }), si, club.id, target, ro)).join('');
                if (S.mode === 'classe' && !teams.length) {
                    boxes = `<div class="eq-box team ${target ? 'target' : ''}" data-drop="${si}|${club.id}|new" data-act="box">
                        <div class="t">Équipe <span class="b">0</span></div><span class="eq-muted">Pas d'équipe</span></div>`;
                }
                if (S.mode === 'libre') boxes += `<div><button class="ghost small" data-act="add-team" data-sec="${si}" data-club="${club.id}" ${ro ? 'disabled' : ''}>+ Équipe</button></div>`;
                h += `<div class="eq-club" data-search="${esc((club.name + ' ' + club.code + ' ' + club.archers.map((a) => a.name).join(' ')).toLowerCase())}">
                    <div class="eq-club-name"><b>${esc(club.name)}</b><br><span class="eq-muted">${esc(club.code)}</span>
                    ${club.native ? `<br><span class="b blue" title="Classement par équipes actuel d'Ianseo">Ianseo : ${club.native.rank || '—'}ᵉ, ${club.native.score}</span>` : ''}</div>
                    <div class="eq-teams">${boxes}</div>
                    <div class="eq-box ${target ? 'target' : ''}" data-drop="${si}|${club.id}|0" data-act="box">
                        <div class="t">Non retenus <span class="b">${free.length}</span></div>
                        ${free.map(chip).join('') || '<span class="eq-muted">—</span>'}</div>
                </div>`;
            }
            h += '</section>';
        });
        return h + `<div class="eq-actions"><button class="ghost" data-act="to2">← Catégories</button><span class="eq-grow"></span>
            <button data-act="to4">Vérifier →</button></div>`;
    }

    function applyFilter() {
        const f = S.filter.trim().toLowerCase();
        document.querySelectorAll('#eq .eq-club').forEach((el) => { el.style.display = !f || el.dataset.search.includes(f) ? '' : 'none'; });
        document.querySelectorAll('#eq .eq-cat').forEach((sec) => {
            sec.style.display = [...sec.querySelectorAll('.eq-club')].some((el) => el.style.display !== 'none') ? '' : 'none';
        });
    }

    /** Déplace des archers : n = numéro d'équipe, 0 = non retenus, 'new' = nouvelle équipe (tous dans la même). */
    function moveArchers(ids, si, club, n) {
        if (readOnly()) return;
        ids = ids.filter((id) => { const a = S.archers[id]; return a && a.sec === si && a.club === club; });
        if (ids.length) {
            for (const t of S.teams) t.members = t.members.filter((m) => !ids.includes(m));
            if (n === 'new') S.teams.push({ sec: si, club, n: S.nextN++, members: ids });
            else if (+n > 0) {
                const t = S.teams.find((x) => x.sec === si && x.club === club && x.n === +n);
                if (t) t.members.push(...ids);
            }
            // Mode classe : une équipe vidée redevient « pas d'équipe ».
            if (S.mode === 'classe') S.teams = S.teams.filter((t) => t.members.length);
        }
        S.sel = new Set();
        render();
    }

    /** Équipes du serveur ({sec: clé, club, members}) vers le modèle de l'écran. */
    function importTeams(list) {
        const index = new Map(S.sections.map((s, i) => [s.key, i]));
        S.teams = list.filter((t) => index.has(t.sec)).map((t) => ({ sec: index.get(t.sec), club: t.club, n: S.nextN++, members: t.members.slice() }));
    }

    const teamsPayload = () => S.teams.filter((t) => t.members.length).map((t) => ({ club: t.club, members: t.members }));

    // ── Étape 4 : vérifier et appliquer ──────────────────────────────────────

    function step4() {
        const P = S.plan;
        if (!P) return '<p class="eq-muted">Calcul de l\'aperçu…</p>';
        const started = P.state !== 'BEFORE';
        const nTeams = P.sections.reduce((n, s) => n + s.teams.length, 0);
        const nAlerts = P.sections.reduce((n, s) => n + s.teams.filter((t) => t.alerts.length).length, 0) + (P.alerts || []).length;
        const kpi = (n, label) => `<div class="eq-kpi"><b>${n}</b><span class="eq-muted">${label}</span></div>`;
        let h = '<div class="eq-kpis">' + kpi(nTeams, 'équipe(s)')
            + (P.mode === 'classe' ? kpi(P.changes.length, 'archer(s) modifié(s) dans Ianseo') : kpi(P.replaced.length, 'équipe(s) libre(s) remplacée(s)'))
            + kpi(nAlerts, 'alerte(s) à regarder') + '</div>';
        h += alertsHtml(P.alerts);
        if (!P.sections.length) h += '<div class="eq-card eq-muted">Aucune équipe.</div>';
        for (const s of P.sections) {
            h += `<div class="eq-card"><h3>${esc(s.label)}${s.sub ? ` <span class="b">${esc(s.sub)}</span>` : ''}</h3>`;
            if (!s.teams.length) h += '<p class="eq-muted">Aucune équipe.</p>';
            else {
                h += `<table class="eq-table"><tr><th>Rang${started ? '' : ' prévu'}</th><th>Équipe</th><th>Archers</th>
                    ${started ? (P.mode === 'classe' ? '<th>Score actuel → prévu</th>' : '<th>Score</th>') : ''}<th>Contrôles</th></tr>`
                    + s.teams.map((t) => `<tr><td class="num">${t.rank ?? '—'}</td><td>${esc(t.name)}</td>
                        <td>${t.members.map((m) => started && t.retained.includes(m) ? `<b>${esc(m)}</b>` : esc(m)).join('<br>')}</td>
                        ${started ? `<td class="num">${P.mode === 'classe' ? `${t.before ?? '—'} → ` : ''}<b>${t.score ?? '—'}</b></td>` : ''}
                        <td>${t.alerts.length ? t.alerts.map((a) => `<span class="b orange">${esc(a)}</span>`).join('<br>') : '<span class="b ok">OK</span>'}</td></tr>`).join('')
                    + '</table>';
                if (started) h += '<p class="eq-muted">En gras : les 3 meilleurs scores, ceux qui comptent.</p>';
                else h += '<p class="eq-muted">Avant le tir, le rang n\'a pas de sens : il apparaîtra avec les scores.</p>';
            }
            if (s.gone && s.gone.length) h += `<div class="eq-banner eq-orange">Équipe(s) actuellement classée(s) qui disparaîtront : ${esc(s.gone.join(', '))}</div>`;
            h += '</div>';
        }
        if (P.mode === 'classe') {
            h += `<div class="eq-card"><h3>Ce qui change dans Ianseo : case « Équipe » de ${P.changes.length} archer(s)</h3>`;
            if (!P.changes.length) h += '<p class="eq-muted">Rien : les inscriptions correspondent déjà à cette composition.</p>';
            else {
                h += '<details><summary>Voir le détail</summary><table class="eq-table"><tr><th>Archer</th><th>Club</th><th>Catégorie</th><th>Équipe</th></tr>'
                    + P.changes.map((c) => `<tr><td>${esc(c.name)}</td><td>${esc(c.club)}</td><td>${esc(c.cat)}${c.why ? ` <span class="b orange">${esc(c.why)}</span>` : ''}</td>
                        <td><span class="eq-old">${c.old ? 'oui' : 'non'}</span> → <span class="eq-new">${c.new ? 'oui' : 'non'}</span></td></tr>`).join('') + '</table></details>';
            }
            h += '</div>';
        } else {
            h += `<div class="eq-card"><h3>Équipes libres déjà enregistrées remplacées : ${P.replaced.length}</h3>
                ${P.replaced.length ? '<ul>' + P.replaced.map((r) => `<li>${esc(r)}</li>`).join('') + '</ul>' : '<p class="eq-muted">Aucune.</p>'}
                <p class="eq-muted">Rien n'est écrit dans les tables d'Ianseo : les équipes libres sont gardées par le module.</p></div>`;
        }
        const needConfirm = P.mode === 'classe' && P.state === 'STARTED';
        h += `<div class="eq-save"><span><b>Avant d'appliquer : avez-vous sauvegardé la compétition ?</b> Le module est indépendant d'Ianseo,
            sans garantie. Vous pourrez annuler cette application depuis le tableau de bord.</span>
            <a class="eq-btn ghost" href="${esc(C.export)}" target="_blank">Exporter la compétition</a></div>`;
        return h + `<div class="eq-actions"><button class="ghost" data-act="to3">← Composition</button><span class="eq-grow"></span>
            ${needConfirm ? `<label class="eq-check eq-banner eq-red" style="margin:0"><input type="checkbox" data-field="confirm" ${S.confirm ? 'checked' : ''}>
                Je confirme : le tir a commencé</label>` : ''}
            <button data-act="apply" ${P.ok && !readOnly() && !S.busy && (!needConfirm || S.confirm) ? '' : 'disabled'}>${P.mode === 'classe' ? 'Appliquer' : 'Enregistrer les équipes'}</button></div>`;
    }

    // ── Fin : après l'application ────────────────────────────────────────────

    function stepFin() {
        return `<div class="eq-card eq-fin" role="status"><h3>${S.mode === 'classe' ? 'Équipes appliquées' : 'Équipes enregistrées'}</h3>
            <p>${esc(S.done)}</p>
            <div class="eq-actions"><a class="eq-btn" href="${esc(C.home)}">Retour au tableau de bord</a>
            ${S.mode === 'classe' ? `<a class="eq-btn ghost" href="${esc(nativePrintUrl())}" target="_blank">Imprimer le classement (Ianseo)</a>`
                : `<a class="eq-btn ghost" href="${esc(C.teamsPdf)}" target="_blank">Imprimer le classement (PDF)</a>`}
            <button class="ghost" data-act="to1">Composer d'autres équipes</button></div>
            <p class="eq-muted">Une erreur ? « Annuler cette application » sur le <a href="${esc(C.home)}">tableau de bord</a>.</p></div>`;
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    async function reloadInit() {
        const r = await api('init');
        if (r.error) { $app().innerHTML = `<div class="eq-banner eq-red">${esc(r.msg)}</div>`; return false; }
        S.init = r;
        return true;
    }

    const ACTIONS = {
        'pick-mode'(el) {
            S.mode = el.dataset.mode;
            S.done = null;
            go(2);
        },
        how(el, ev) {
            // « Comment faire ? » reste ouvert d'un rendu à l'autre.
            ev.preventDefault();
            S.how = !S.how;
            render();
        },
        'cls-sex'(el) {
            const sex = el.dataset.sex;
            const all = S.init.classes.filter((c) => sex === 'all' || String(c.sex) === sex).map((c) => c.id);
            S.crit.classes = sex === 'none' ? [] : [...new Set(S.crit.classes.concat(all))];
            render();
        },
        async load() {
            const r = await api('load', params());
            if (r.error) { S.msg = ['red', r.msg]; render(); return; }
            S.sections = r.sections;
            S.archers = {};
            r.sections.forEach((s, si) => { for (const club of s.clubs) for (const a of club.archers) S.archers[a.id] = Object.assign(a, { sec: si, club: club.id }); });
            importTeams(r.teams);
            S.sel = new Set();
            S.done = null;
            go(3);
        },
        to1() { go(1); },
        to2() { go(2); },
        to3() { go(3); },
        async to4() {
            S.plan = null;
            S.confirm = false;
            go(4);
            const r = await api('preview', Object.assign(params(), { teams: teamsPayload() }));
            S.plan = r.error ? { ok: false, mode: S.mode, alerts: [['error', r.msg]], sections: [], changes: [], replaced: [], state: 'BEFORE' } : r.plan;
            render();
        },
        async propose() {
            if (S.teams.some((t) => t.members.length) && !confirm('Remplacer la composition affichée par une proposition automatique ?')) return;
            const r = await api('propose', params());
            if (r.error) { S.msg = ['red', r.msg]; render(); return; }
            importTeams(r.teams);
            S.msg = ['info', `Proposition : ${S.teams.length} équipe(s). Ajustez, puis « Vérifier ».`];
            render();
        },
        clear() {
            if (!confirm('Retirer tous les archers de toutes les équipes affichées ?')) return;
            S.teams = [];
            render();
        },
        'add-team'(el) {
            S.teams.push({ sec: +el.dataset.sec, club: +el.dataset.club, n: S.nextN++, members: [] });
            render();
        },
        'del-team'(el, ev) {
            ev.stopPropagation();
            S.teams = S.teams.filter((t) => t.n !== +el.dataset.n);
            render();
        },
        chip(el, ev) {
            ev.stopPropagation();
            const id = +el.dataset.id;
            if (ev.ctrlKey || ev.metaKey) {
                // Ajout ou retrait ; un archer d'un autre club ou d'une autre section recommence la sélection.
                const first = S.sel.size ? S.archers[S.sel.values().next().value] : null;
                const a = S.archers[id];
                if (first && (first.sec !== a.sec || first.club !== a.club)) S.sel = new Set([id]);
                else if (S.sel.has(id)) S.sel.delete(id);
                else S.sel.add(id);
            } else {
                S.sel = S.sel.size === 1 && S.sel.has(id) ? new Set() : new Set([id]);
            }
            render();
        },
        box(el) {
            if (!S.sel.size) return;
            const [si, club, n] = el.dataset.drop.split('|');
            moveArchers([...S.sel], +si, +club, n);
        },
        async apply() {
            S.busy = true;
            render();
            const r = await api('apply', Object.assign(params(), { teams: teamsPayload(), plan: S.plan.token, confirm: S.confirm }));
            S.busy = false;
            if (r.error) {
                if (r.plan) S.plan = r.plan;
                S.msg = ['red', r.msg];
                render();
                return;
            }
            await reloadInit();
            S.confirm = false;
            S.done = r.msg;
            go(FIN);
        },
    };

    /** Préréglage venu du tableau de bord (« Modifier ces équipes ») : mode, critères, puis composition. */
    async function applyPreset() {
        const p = C.preset || {};
        if (p.mode) S.mode = p.mode;
        const divs = new Set(S.init.divisions.map((d) => d.id));
        const cls = new Set(S.init.classes.map((c) => c.id));
        S.crit.divs = (p.divs || []).filter((d) => divs.has(d));
        S.crit.classes = (p.classes || []).filter((c) => cls.has(c));
        if (p.compose && S.crit.divs.length && S.crit.classes.length) await ACTIONS.load();
        else go(p.mode ? 2 : 1);
    }

    function onField(el) {
        const f = el.dataset.field;
        const num = (v, min, max) => Math.max(min, Math.min(max, parseInt(v, 10) || 0));
        if (f === 'div' || f === 'cls') {
            const key = f === 'div' ? 'divs' : 'classes';
            S.crit[key] = el.checked ? [...new Set(S.crit[key].concat(el.value))] : S.crit[key].filter((x) => x !== el.value);
            render();
        }
        else if (f === 's-min') { S.settings.size_min = num(el.value, 3, 8); if (S.settings.size_max < S.settings.size_min) S.settings.size_max = S.settings.size_min; }
        else if (f === 's-max') { S.settings.size_max = num(el.value, S.settings.size_min, 8); }
        else if (f === 'filter') { S.filter = el.value; applyFilter(); }
        else if (f === 'confirm') { S.confirm = el.checked; render(); }
    }

    // ── Branchements ─────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', async () => {
        const root = $app();
        root.addEventListener('click', (ev) => {
            const el = ev.target.closest('[data-act]');
            if (!el || el.disabled || !ACTIONS[el.dataset.act]) return;
            ACTIONS[el.dataset.act](el, ev);
        });
        root.addEventListener('change', (ev) => { if (ev.target.dataset.field && ev.target.dataset.field !== 'filter') onField(ev.target); });
        root.addEventListener('input', (ev) => { if (ev.target.dataset.field === 'filter') onField(ev.target); });
        root.addEventListener('dragstart', (ev) => {
            const el = ev.target.closest && ev.target.closest('.chip');
            if (!el) return;
            // Archer sélectionné : toute la sélection part avec lui.
            const id = +el.dataset.id;
            const ids = S.sel.has(id) ? [...S.sel] : [id];
            ev.dataTransfer.setData('text/plain', ids.join(','));
            ev.dataTransfer.effectAllowed = 'move';
            el.classList.add('drag');
        });
        root.addEventListener('dragend', (ev) => { if (ev.target.classList) ev.target.classList.remove('drag'); });
        root.addEventListener('dragover', (ev) => {
            const box = ev.target.closest('[data-drop]');
            if (!box) return;
            ev.preventDefault();
            box.classList.add('over');
        });
        root.addEventListener('dragleave', (ev) => {
            const box = ev.target.closest('[data-drop]');
            if (box && !box.contains(ev.relatedTarget)) box.classList.remove('over');
        });
        root.addEventListener('drop', (ev) => {
            const box = ev.target.closest('[data-drop]');
            if (!box) return;
            ev.preventDefault();
            const [si, club, n] = box.dataset.drop.split('|');
            moveArchers(ev.dataTransfer.getData('text/plain').split(',').map(Number), +si, +club, n);
        });
        document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape' && S.step === 3 && S.sel.size) { S.sel = new Set(); render(); } });
        if (await reloadInit()) await applyPreset();
    });
})();
