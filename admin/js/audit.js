/**
 * AEO Audit Report - Admin JS
 */
(function () {
    'use strict';

    var wrap = document.getElementById('aeo-audit-wrap');
    if (!wrap) return;

    var loading  = document.getElementById('aeo-audit-loading');
    var errorBox = document.getElementById('aeo-audit-error');
    var content  = document.getElementById('aeo-audit-content');

    /* ── Tabs ─────────────────────────────────────────── */

    function initTabs() {
        var tabs = wrap.querySelectorAll('.nav-tab');
        var panels = wrap.querySelectorAll('.aeo-tab-panel');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                var target = this.getAttribute('data-tab');

                tabs.forEach(function (t) { t.classList.remove('nav-tab-active'); });
                panels.forEach(function (p) { p.style.display = 'none'; });

                this.classList.add('nav-tab-active');
                var panel = document.getElementById('tab-' + target);
                if (panel) panel.style.display = '';
            });
        });
    }

    /* ── Accordions ───────────────────────────────────── */

    function initAccordions() {
        wrap.addEventListener('click', function (e) {
            var header = e.target.closest('.aeo-accordion-header');
            if (!header) return;

            var item = header.parentElement;
            var body;

            // Table-based accordion (Scoreboard): body is the next sibling <tr>.
            if (item.tagName === 'TR') {
                body = item.nextElementSibling;
                if (!body || !body.classList.contains('aeo-accordion-body')) return;
            } else {
                // Div-based accordion (Findings, Opportunities).
                body = item.querySelector('.aeo-accordion-body');
                if (!body) return;
            }

            var isOpen = item.classList.contains('is-open');

            // Close all other open items in the same list/container
            var container = item.parentElement;
            container.querySelectorAll('.aeo-accordion-item.is-open').forEach(function (openItem) {
                if (openItem === item) return;
                openItem.classList.remove('is-open');
                var openBody = openItem.querySelector('.aeo-accordion-body');
                if (openBody) openBody.style.display = 'none';
                var openArrow = openItem.querySelector('.aeo-accordion-arrow');
                if (openArrow) openArrow.classList.remove('is-open');
            });

            item.classList.toggle('is-open');
            body.style.display = isOpen ? 'none' : '';
            var arrow = item.querySelector('.aeo-accordion-arrow');
            if (arrow) arrow.classList.toggle('is-open', !isOpen);
        });
    }

    /* ── Score Colors (matching site) ─────────────────── */

    function scoreColor100(score) {
        if (score >= 70) return '#34a853';
        if (score >= 50) return '#c5a200';
        return '#ea4335';
    }

    function scoreBg100(score) {
        if (score >= 70) return 'rgba(52,168,83,0.12)';
        if (score >= 50) return 'rgba(197,162,0,0.12)';
        return 'rgba(234,67,53,0.12)';
    }

    function scoreColor10(score) {
        if (score >= 7) return '#34a853';
        if (score >= 5) return '#c5a200';
        return '#ea4335';
    }

    function scoreBg10(score) {
        if (score >= 7) return 'rgba(52,168,83,0.12)';
        if (score >= 5) return 'rgba(197,162,0,0.12)';
        return 'rgba(234,67,53,0.12)';
    }

    /* ── Criterion Slugs (for knowledge links) ──────── */

    /* Legacy slugs (audits with < 40 criteria) */
    var LEGACY_CRITERION_SLUGS = {
        1:'llms-txt',2:'structured-data',3:'qa-content',4:'clean-html',
        5:'entity-authority',6:'robots-txt-ai',7:'faq-section',8:'original-data',
        9:'internal-linking',10:'semantic-html',11:'schema-coverage-ratio',
        12:'content-freshness',13:'rss-feed-presence',14:'sitemap-completeness',
        15:'canonical-url-strategy',16:'content-licensing',17:'fact-density',
        18:'definition-patterns',19:'table-list-extractability',20:'content-velocity',
        21:'author-expert-schema',22:'direct-answer-density',23:'speakable-schema',
        24:'query-answer-alignment',25:'content-cannibalization',26:'visible-date-signal',
        27:'topic-coherence',28:'content-depth',
        29:'citation-ready-writing',30:'answer-first-placement',
        31:'evidence-packaging',32:'entity-disambiguation',
        33:'extraction-friction',34:'image-context-ai',
        35:'duplicate-content',36:'cross-page-duplication'
    };

    /* Current aeorank slugs (40+ criteria) */
    var CURRENT_CRITERION_SLUGS = {
        1:'llms-txt',2:'structured-data',3:'qa-content',4:'clean-html',
        5:'entity-authority',6:'robots-txt-ai',7:'faq-section',8:'original-data',
        9:'internal-linking',10:'semantic-html',11:'content-freshness',
        12:'sitemap-completeness',13:'rss-feed-presence',14:'table-list-extractability',
        15:'definition-patterns',16:'direct-answer-density',17:'content-licensing',
        18:'author-expert-schema',19:'fact-density',20:'canonical-url-strategy',
        21:'content-velocity',22:'schema-coverage-ratio',23:'speakable-schema',
        24:'query-answer-alignment',25:'content-cannibalization',26:'visible-date-signal',
        27:'topic-coherence',28:'content-depth',
        29:'helpful-purpose-alignment',30:'first-hand-experience-signals',
        31:'creator-transparency',32:'methodology-transparency',
        33:'citation-ready-writing',34:'answer-first-placement',
        35:'evidence-packaging',36:'entity-disambiguation',
        37:'extraction-friction',38:'image-context-ai',
        39:'duplicate-content',40:'cross-page-duplication',
        41:'response-efficiency',42:'critical-path-efficiency',43:'document-weight',
        44:'internationalization-signals',45:'answer-capsule-pattern',
        46:'entity-density',47:'owned-data-density',48:'sentence-atomicity'
    };

    var CRITERION_SLUGS = LEGACY_CRITERION_SLUGS;

    function getCriterionSlug(id, scorecard) {
        if (scorecard && scorecard.length >= 40 && CURRENT_CRITERION_SLUGS[id]) {
            return CURRENT_CRITERION_SLUGS[id];
        }
        return CRITERION_SLUGS[id] || CURRENT_CRITERION_SLUGS[id];
    }

    var KNOWLEDGE_BASE_URL = 'https://www.aeocontent.ai/knowledge/';

    function statusColor(status) {
        var s = (status || '').toUpperCase();
        if (['MISSING', 'NEARLY EMPTY', 'POOR', 'WEAK'].indexOf(s) !== -1) return '#ea4335';
        if (['PARTIAL', 'MODERATE'].indexOf(s) !== -1) return '#c5a200';
        return '#34a853';
    }

    function statusLabel(status) {
        var s = (status || '').toLowerCase();
        if (['missing', 'nearly empty', 'poor', 'weak'].indexOf(s) !== -1) return 'critical';
        if (['partial', 'moderate'].indexOf(s) !== -1) return 'moderate';
        return 'good';
    }

    /* ── Finding Badge Styles (matching site) ───────────── */

    var TYPE_STYLES = {
        'Good':     { bg: 'rgba(16,185,129,0.2)',  color: '#059669' },
        'Exists':   { bg: 'rgba(16,185,129,0.2)',  color: '#059669' },
        'Present':  { bg: 'rgba(16,185,129,0.2)',  color: '#059669' },
        'Bad':      { bg: 'rgba(239,68,68,0.2)',   color: '#dc2626' },
        'Critical': { bg: 'rgba(185,28,28,0.25)',  color: '#dc2626' },
        'Missing':  { bg: 'rgba(249,115,22,0.2)',  color: '#ea580c' },
        'Issue':    { bg: 'rgba(234,179,8,0.2)',   color: '#ca8a04' },
        'Fix':      { bg: 'rgba(59,130,246,0.2)',  color: '#2563eb' },
        'Volume':   { bg: 'rgba(59,130,246,0.2)',  color: '#2563eb' },
        'Calc':     { bg: 'rgba(168,85,247,0.2)',  color: '#9333ea' },
        'Test':     { bg: 'rgba(107,114,128,0.2)', color: '#4b5563' },
        'Note':     { bg: 'rgba(107,114,128,0.2)', color: '#4b5563' },
        'Current':  { bg: 'rgba(107,114,128,0.2)', color: '#4b5563' },
        'Bonus':    { bg: 'rgba(245,158,11,0.2)',  color: '#d97706' },
        'Impact':   { bg: 'rgba(62,118,230,0.2)',  color: '#3b82f6' },
    };

    var SEVERITY_STYLES = {
        'WORKING':        { bg: 'rgba(16,185,129,0.15)',  color: '#059669', border: 'rgba(16,185,129,0.3)' },
        'GOOD':           { bg: 'rgba(16,185,129,0.15)',  color: '#059669', border: 'rgba(16,185,129,0.3)' },
        'GOOD PATTERN':   { bg: 'rgba(16,185,129,0.15)',  color: '#059669', border: 'rgba(16,185,129,0.3)' },
        'QUICK WIN':      { bg: 'rgba(16,185,129,0.15)',  color: '#059669', border: 'rgba(16,185,129,0.3)' },
        'PARTIAL':        { bg: 'rgba(234,179,8,0.15)',   color: '#ca8a04', border: 'rgba(234,179,8,0.3)' },
        'CONFUSING':      { bg: 'rgba(234,179,8,0.15)',   color: '#ca8a04', border: 'rgba(234,179,8,0.3)' },
        'INCONSISTENT':   { bg: 'rgba(234,179,8,0.15)',   color: '#ca8a04', border: 'rgba(234,179,8,0.3)' },
        'SPARSE':         { bg: 'rgba(234,179,8,0.15)',   color: '#ca8a04', border: 'rgba(234,179,8,0.3)' },
        'MEDIUM':         { bg: 'rgba(234,179,8,0.15)',   color: '#ca8a04', border: 'rgba(234,179,8,0.3)' },
        'MISSING':        { bg: 'rgba(249,115,22,0.15)',  color: '#ea580c', border: 'rgba(249,115,22,0.3)' },
        'ADD':            { bg: 'rgba(249,115,22,0.15)',  color: '#ea580c', border: 'rgba(249,115,22,0.3)' },
        'HIGH':           { bg: 'rgba(249,115,22,0.15)',  color: '#ea580c', border: 'rgba(249,115,22,0.3)' },
        'FIX':            { bg: 'rgba(59,130,246,0.15)',  color: '#2563eb', border: 'rgba(59,130,246,0.3)' },
        'MEASUREMENT':    { bg: 'rgba(59,130,246,0.15)',  color: '#2563eb', border: 'rgba(59,130,246,0.3)' },
        'FIX IMMEDIATELY':{ bg: 'rgba(239,68,68,0.15)',   color: '#dc2626', border: 'rgba(239,68,68,0.3)' },
        'REWRITE':        { bg: 'rgba(239,68,68,0.15)',   color: '#dc2626', border: 'rgba(239,68,68,0.3)' },
        'CRITICAL':       { bg: 'rgba(239,68,68,0.15)',   color: '#dc2626', border: 'rgba(239,68,68,0.3)' },
        'PERFORMANCE':    { bg: 'rgba(168,85,247,0.15)',  color: '#9333ea', border: 'rgba(168,85,247,0.3)' },
        'CLUTTER':        { bg: 'rgba(107,114,128,0.15)', color: '#4b5563', border: 'rgba(107,114,128,0.3)' },
        'PLATFORM LIMIT': { bg: 'rgba(107,114,128,0.15)', color: '#4b5563', border: 'rgba(107,114,128,0.3)' },
        'BIG OPPORTUNITY':{ bg: 'rgba(249,115,22,0.15)',  color: '#ea580c', border: 'rgba(249,115,22,0.3)' },
        'AEO GOLDMINE':   { bg: 'rgba(62,118,230,0.2)',   color: '#3b82f6', border: 'rgba(62,118,230,0.4)' },
        'AEO CORE':       { bg: 'rgba(62,118,230,0.2)',   color: '#3b82f6', border: 'rgba(62,118,230,0.4)' },
        'CORE AEO':       { bg: 'rgba(62,118,230,0.2)',   color: '#3b82f6', border: 'rgba(62,118,230,0.4)' },
        'AEO deliverable':{ bg: 'rgba(62,118,230,0.2)',   color: '#3b82f6', border: 'rgba(62,118,230,0.4)' },
    };

    var FALLBACK_STYLE = { bg: 'rgba(107,114,128,0.15)', color: '#4b5563', border: 'rgba(107,114,128,0.3)' };

    function typeBadgeStyle(type) {
        var s = TYPE_STYLES[type] || FALLBACK_STYLE;
        return 'background:' + s.bg + ';color:' + s.color + ';';
    }

    function severityBadgeStyle(severity) {
        var s = SEVERITY_STYLES[severity] || FALLBACK_STYLE;
        return 'background:' + s.bg + ';color:' + s.color + ';border:1px solid ' + s.border + ';';
    }

    function formatDate(iso) {
        var d = new Date(iso);
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
    }

    /* ── Category / Pillar Groups ────────────────────── */

    var CATEGORIES_V1 = [
        { key: 'content',   label: 'Content',       color: '#34a853', bg: '#e6f4ea', ids: [3, 7, 8, 11, 15, 16, 19, 24, 25] },
        { key: 'structure', label: 'Structure',      color: '#4285f4', bg: '#e8f0fe', ids: [2, 4, 10, 14, 22, 23] },
        { key: 'discovery', label: 'Discovery',      color: '#9334e6', bg: '#f3e8fd', ids: [1, 6, 9, 12, 13] },
        { key: 'trust',     label: 'Trust Signals',  color: '#f9ab00', bg: '#fef7e0', ids: [5, 17, 18, 20, 21, 26] },
    ];

    var CATEGORIES_V2 = [
        { key: 'substance',    label: 'Content Substance',     color: '#34a853', bg: '#e6f4ea', weight: '~55%', ids: [27, 8, 28, 19, 16, 3, 24, 7] },
        { key: 'organization', label: 'Content Organization',  color: '#4285f4', bg: '#e8f0fe', weight: '~30%', ids: [5, 9, 11, 2, 18, 14, 15, 26, 10, 4] },
        { key: 'plumbing',     label: 'Technical Plumbing',    color: '#ea4335', bg: '#fce8e6', weight: '~15%', ids: [25, 1, 6, 21, 17, 12, 20, 13, 22, 23] },
    ];

    /* 5-pillar groups for current aeorank v3+ audits (34–48 criteria) */
    var PILLAR_GROUPS = [
        { key: 'answer',    label: 'Answer Readiness',     color: '#34a853', bg: '#e6f4ea', weight: '~46%', ids: [8, 19, 27, 28, 29, 30, 33, 34, 35, 39, 40, 45] },
        { key: 'structure', label: 'Content Structure',     color: '#4285f4', bg: '#e8f0fe', weight: '~25%', ids: [3, 7, 14, 15, 16, 24, 36, 46, 48] },
        { key: 'trust',     label: 'Trust & Authority',     color: '#f9ab00', bg: '#fef7e0', weight: '~15%', ids: [2, 5, 9, 11, 18, 31, 32, 47] },
        { key: 'technical', label: 'Technical Foundation',  color: '#9334e6', bg: '#f3e8fd', weight: '~8%',  ids: [4, 10, 22, 23, 26, 37, 38, 41, 42, 43] },
        { key: 'discovery', label: 'AI Discovery',          color: '#ea4335', bg: '#fce8e6', weight: '~6%',  ids: [1, 6, 12, 13, 17, 20, 21, 25, 44] },
    ];

    function isV2(scorecard) {
        return scorecard.length >= 28 || scorecard.some(function (s) { return s.id === 27 || s.id === 28; });
    }

    function isV3(scorecard) {
        return scorecard.length >= 34 || scorecard.some(function (s) { return s.id === 29 || s.id === 33 || s.id === 35 || s.id === 36; });
    }

    function getCategories(scorecard) {
        if (isV3(scorecard)) return PILLAR_GROUPS;
        if (isV2(scorecard)) return CATEGORIES_V2;
        return CATEGORIES_V1;
    }

    function getCategoryForId(id, cats) {
        for (var i = 0; i < cats.length; i++) {
            if (cats[i].ids.indexOf(id) !== -1) return cats[i];
        }
        return { key: 'other', label: 'Other', color: '#646970', bg: '#f0f0f1', ids: [] };
    }

    /* ── SVG Score Circle (matches site design) ───────── */

    function renderScoreCircle(score, max, size) {
        max = max || 100;
        size = size || 100;
        var radius = (size / 2) - 6;
        var circumference = 2 * Math.PI * radius;
        var pct = max > 0 ? score / max : 0;
        var progress = pct * circumference;
        var color = max === 100 ? scoreColor100(score) : scoreColor10(score);
        var bgColor = max === 100 ? scoreBg100(score) : scoreBg10(score);
        var numSize = size >= 100 ? 38 : size >= 60 ? 22 : 16;
        var subSize = size >= 100 ? 14 : size >= 60 ? 10 : 8;
        var strokeW = size >= 100 ? 3 : 2.5;
        var cx = size / 2;
        var cy = size / 2;

        return '<div class="aeo-score-circle" style="width:' + size + 'px;height:' + size + 'px;position:relative;">' +
            '<div style="position:absolute;inset:0;border-radius:50%;background:' + bgColor + ';"></div>' +
            '<svg width="' + size + '" height="' + size + '" style="position:absolute;inset:0;transform:rotate(-90deg);">' +
                '<circle cx="' + cx + '" cy="' + cy + '" r="' + radius + '" fill="none" stroke="#e0e0e0" stroke-width="' + strokeW + '"/>' +
                '<circle cx="' + cx + '" cy="' + cy + '" r="' + radius + '" fill="none" stroke="' + color + '" stroke-width="' + strokeW + '" ' +
                    'stroke-dasharray="' + progress + ' ' + (circumference - progress) + '" stroke-linecap="round"/>' +
            '</svg>' +
            '<div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">' +
                '<span style="font-size:' + numSize + 'px;font-weight:700;color:' + color + ';line-height:1;">' + score + '</span>' +
                '<span style="font-size:' + subSize + 'px;font-weight:700;color:#999;line-height:1;">/' + max + '</span>' +
            '</div>' +
        '</div>';
    }

    /* ── Render Overview Tab ──────────────────────────── */

    function renderOverview(audit) {
        var html = '';
        var scorecard = audit.scorecard || [];
        var cats = getCategories(scorecard);
        var v3 = isV3(scorecard);
        var v2 = v3 || isV2(scorecard);
        var passingCount = scorecard.filter(function (s) { return s.score >= 5; }).length;

        // Hero row: favicon + domain + verdict + score + legend
        html += '<div class="aeo-hero">';

        // Left: favicon + domain
        html += '<div class="aeo-hero-left">';
        var faviconUrl = aeocasAudit.favicon || 'https://www.google.com/s2/favicons?domain=' + encodeURIComponent(audit.domain) + '&sz=48';
        html += '<img class="aeo-hero-favicon" src="' + faviconUrl + '" alt="" width="48" height="48"/>';
        html += '<div class="aeo-hero-meta">';
        html += '<h2 class="aeo-hero-domain">' + esc(audit.domain) + '</h2>';
        html += '<div class="aeo-hero-tags">';
        if (audit.category) {
            html += '<span class="aeo-tag">' + esc(audit.category) + '</span>';
        }
        var auditDate = audit.updated_at || audit.created_at;
        if (auditDate) {
            html += '<span class="aeo-tag aeo-tag-muted">' + formatDate(auditDate) + '</span>';
        }
        html += '</div>';
        html += '</div>';
        html += '</div>';

        // Center: verdict
        html += '<div class="aeo-hero-center">';
        html += '<p class="aeo-hero-verdict">' + esc(audit.verdict || 'Moderate AI visibility with ' + passingCount + ' of ' + scorecard.length + ' criteria passing.') + '</p>';
        if (v3) {
            html += '<span class="aeo-v2-badge">5-PILLAR SCORING</span>';
        } else if (v2) {
            html += '<span class="aeo-v2-badge">V2 SCORING</span>';
        }
        html += '</div>';

        // Right: score circle + legend (circle is clickable → breakdown modal)
        html += '<div class="aeo-hero-right">';
        html += '<button type="button" class="aeo-score-trigger" aria-label="View score breakdown">';
        html +=   renderScoreCircle(audit.overall_score, 100, 100);
        html +=   '<span class="aeo-score-trigger-hint">Click for breakdown</span>';
        html += '</button>';
        html += '<div class="aeo-score-legend">';
        html += '<div class="aeo-legend-item"><span class="aeo-legend-dot" style="background:#ea4335;"></span> 0-49</div>';
        html += '<div class="aeo-legend-item"><span class="aeo-legend-dot" style="background:#c5a200;"></span> 50-69</div>';
        html += '<div class="aeo-legend-item"><span class="aeo-legend-dot" style="background:#34a853;"></span> 69-100</div>';
        html += '</div>';
        html += '</div>';

        html += '</div>';

        // Bottom line
        if (audit.bottom_line) {
            html += '<div class="aeo-bottom-line">';
            html += '<div class="aeo-bottom-line-label">Bottom Line</div>';
            html += '<p class="aeo-bottom-line-text">' + esc(audit.bottom_line) + '</p>';
            html += '</div>';
        }

        // Category cards
        html += '<div class="aeo-category-cards">';
        cats.forEach(function (cat) {
            var catScores = [];
            scorecard.forEach(function (item) {
                if (cat.ids.indexOf(item.id) !== -1) catScores.push(item.score);
            });
            var avg = catScores.length ? Math.round(catScores.reduce(function (a, b) { return a + b; }, 0) / catScores.length) : 0;

            html += '<div class="aeo-category-card">';
            html += '<div class="aeo-category-card-info">';
            html += '<span class="aeo-cat-badge" style="background:' + cat.bg + ';color:' + cat.color + ';">' + esc(cat.label).toUpperCase() + '</span>';
            if (cat.weight) {
                html += '<div class="aeo-category-card-weight">' + cat.weight + ' of score</div>';
            } else {
                html += '<div class="aeo-category-card-weight">' + catScores.length + ' criteria</div>';
            }
            html += '</div>';
            html += '<div class="aeo-category-card-score">' + renderScoreCircle(avg, 10, 60) + '</div>';
            html += '</div>';
        });
        html += '</div>';

        // Fix summary
        if (audit.fix_summary) {
            var fs = audit.fix_summary;
            html += '<div class="aeo-fix-summary">';
            html += '<div class="aeo-fix-summary-header">';
            html += '<h3>Fix Summary</h3>';
            html += '<div class="aeo-fix-summary-stats">';
            html += '<span class="aeo-fix-stat"><strong>' + fs.criteria_to_fix + '</strong> criteria to fix</span>';
            html += '<span class="aeo-fix-stat"><strong class="aeo-stat-success">+' + fs.total_potential_gain + ' pts</strong> potential gain</span>';
            html += '</div>';
            html += '</div>';
            if (fs.top_3_fixes && fs.top_3_fixes.length) {
                html += '<div class="aeo-fix-top3-list">';
                fs.top_3_fixes.forEach(function (fix, i) {
                    var match = fix.match(/^(.+?)\s*\((\+\d+pts)\)$/);
                    var name = match ? match[1] : fix;
                    var pts = match ? match[2] : '';
                    html += '<div class="aeo-fix-top3-item">';
                    html += '<span class="aeo-fix-top3-rank">' + (i + 1) + '</span>';
                    html += '<span class="aeo-fix-top3-name">' + esc(name) + '</span>';
                    if (pts) html += '<span class="aeo-fix-top3-pts">' + esc(pts) + '</span>';
                    html += '</div>';
                });
                html += '</div>';
            }
            html += '</div>';
        }

        return html;
    }

    /* ── Render Scoreboard Tab ────────────────────────── */

    function renderScoreboard(audit) {
        var scorecard = audit.scorecard || [];
        if (!scorecard.length) return '<p>No scorecard data available.</p>';
        var cats = getCategories(scorecard);

        // Build detailed findings lookup by criterion id
        var findingsMap = {};
        (audit.detailed_findings || []).forEach(function (c) {
            findingsMap[c.id] = c.findings || [];
        });

        var html = '<div class="aeo-scoreboard-list">';

        // Column header row
        html += '<div class="aeo-scoreboard-cols">';
        html += '<span class="aeo-sb-col-criteria">Criteria</span>';
        html += '<span class="aeo-sb-col-status">Status</span>';
        html += '<span class="aeo-sb-col-potential">Potential</span>';
        html += '<span class="aeo-sb-col-score">Score</span>';
        html += '</div>';

        scorecard.forEach(function (item) {
            var cat = getCategoryForId(item.id, cats);
            var sColor = statusColor(item.status);
            var sBg = scoreBg10(item.score);
            var sClr = scoreColor10(item.score);
            var potential = 10 - item.score;
            var potentialHtml = potential > 0
                ? '<span style="color:#34a853;">+ ' + potential + ' pts</span>'
                : '<span style="color:#646970;">max</span>';

            html += '<div class="aeo-accordion-item aeo-finding-group">';
            html += '<div class="aeo-accordion-header aeo-scoreboard-header">';
            html += '<span class="aeo-sb-dot" style="background:' + sColor + ';"></span>';
            html += '<span class="aeo-cat-badge" style="background:' + cat.bg + ';color:' + cat.color + ';">' + esc(cat.label) + '</span>';
            html += '<span class="aeo-sb-col-criteria aeo-finding-name">' + esc(item.criterion) + '</span>';
            html += '<span class="aeo-sb-col-status"><span style="color:' + sColor + ';font-weight:500;">' + statusLabel(item.status) + '</span></span>';
            html += '<span class="aeo-sb-col-potential">' + potentialHtml + '</span>';
            html += '<span class="aeo-sb-col-score"><span class="aeo-score-badge-pill" style="background:' + sBg + ';color:' + sClr + ';">' + item.score + '/10</span></span>';
            html += '<span class="aeo-accordion-arrow">&#8963;</span>';
            html += '</div>';

            // Accordion body: guide links + detailed findings rows
            html += '<div class="aeo-accordion-body" style="display:none;">';

            // Knowledge links bar
            var slug = getCriterionSlug(item.id, scorecard);
            if (slug) {
                html += '<div class="aeo-sb-links-bar">';
                html += '<a href="' + KNOWLEDGE_BASE_URL + slug + '" target="_blank" rel="noopener" class="aeo-sb-guide-link">Read full guide &rarr;</a>';
                html += '<a href="' + KNOWLEDGE_BASE_URL + slug + '" target="_blank" rel="noopener" class="aeo-sb-preview-link">Quick preview</a>';
                html += '</div>';
            }

            var details = findingsMap[item.id] || [];
            if (details.length) {
                details.forEach(function (f, idx) {
                    var rowBg = idx % 2 === 0 ? '#f9f9f9' : '#fff';
                    html += '<div class="aeo-sb-finding-row" style="background:' + rowBg + ';">';
                    html += '<span class="aeo-sb-type-badge" style="' + typeBadgeStyle(f.type) + '">' + esc(f.type) + '</span>';
                    html += '<span class="aeo-sb-finding-desc">' + esc(f.description) + '</span>';
                    html += '<span class="aeo-sb-severity-badge" style="' + severityBadgeStyle(f.severity) + '">' + esc(f.severity) + '</span>';
                    html += '</div>';
                });
            } else {
                html += '<div class="aeo-key-findings" style="padding:12px 14px;">' + esc(item.keyFindings || 'No details available.') + '</div>';
            }
            html += '</div></div>';
        });

        html += '</div>';
        return html;
    }

    /* ── Render Opportunities Tab ─────────────────────── */

    function renderOpportunities(audit) {
        var opps = audit.opportunities || [];
        if (!opps.length) return '<p>No opportunities identified.</p>';

        var html = '<div class="aeo-opportunities-list">';

        opps.forEach(function (opp) {
            var impactColor = '#646970';
            var impact = (opp.impact || '').toLowerCase();
            if (impact === 'high' || impact === 'critical') impactColor = '#ea4335';
            else if (impact === 'medium') impactColor = '#f9ab00';
            else if (impact === 'low') impactColor = '#34a853';

            html += '<div class="aeo-accordion-item aeo-opp-card">';
            html += '<div class="aeo-accordion-header aeo-opp-header">';
            html += '<span class="aeo-accordion-arrow">&#8963;</span>';
            html += '<span class="aeo-opp-impact" style="background:' + impactColor + '20;color:' + impactColor + ';">' + esc(opp.impact || 'N/A') + '</span>';
            html += '<span class="aeo-opp-name">' + esc(opp.name || '') + '</span>';
            if (opp.effort) {
                var effortLower = (opp.effort || '').toLowerCase();
                var effortColor = '#646970';
                if (effortLower === 'low') effortColor = '#34a853';
                else if (effortLower === 'medium') effortColor = '#f9ab00';
                else if (effortLower === 'high') effortColor = '#ea4335';
                html += '<span class="aeo-opp-effort" style="background:' + effortColor + '20;color:' + effortColor + ';border:1px solid ' + effortColor + '30;">' + esc(opp.effort) + '</span>';
            }
            html += '</div>';

            html += '<div class="aeo-accordion-body" style="display:none;">';
            html += '<p>' + esc(opp.description || 'No description.') + '</p>';
            html += '</div></div>';
        });

        html += '</div>';
        return html;
    }

    /* ── Render Site Audit Tab ────────────────────────── */

    function getPageScore(p) {
        if (!p) return 0;
        if (typeof p.pageRankScore === 'number' && p.pageRankScore > 0) return p.pageRankScore;
        if (p.pageRank && typeof p.pageRank.score === 'number') return p.pageRank.score;
        if (typeof p.aeoScore === 'number') return p.aeoScore;
        return 0;
    }

    function getPageIssueCount(p) {
        if (!p) return 0;
        if (Array.isArray(p.issues)) return p.issues.length;
        return 0;
    }

    function buildInLinksMap(audit) {
        var m = {};
        var g = audit && audit.link_graph ? audit.link_graph : {};
        (g.nodes || []).forEach(function (n) { m[n.url] = n.inDegree || 0; });
        return m;
    }

    function collectPageCategories(pages) {
        var seen = {};
        var out = [];
        pages.forEach(function (p) {
            var c = (p.category || '').trim();
            if (!c || seen[c]) return;
            seen[c] = true;
            out.push(c);
        });
        out.sort();
        return out;
    }

    function applySiteAuditFilters(audit) {
        var pages = (audit && audit.pages_reviewed) || [];
        var inLinks = buildInLinksMap(audit);
        var search  = (siteAuditFilters.search || '').toLowerCase().trim();
        var cat     = siteAuditFilters.category;
        var range   = siteAuditFilters.scoreRange;
        var sort    = siteAuditFilters.sort;

        var filtered = pages.filter(function (p) {
            if (cat !== 'all' && (p.category || '') !== cat) return false;
            var score = getPageScore(p);
            if (range === 'low'  && !(score > 0 && score < 40)) return false;
            if (range === 'mid'  && !(score >= 40 && score < 70)) return false;
            if (range === 'high' && !(score >= 70)) return false;
            if (range === 'unscored' && score > 0) return false;
            if (search) {
                var hay = ((p.url || '') + ' ' + (p.title || '')).toLowerCase();
                if (hay.indexOf(search) === -1) return false;
            }
            return true;
        });

        filtered.sort(function (a, b) {
            switch (sort) {
                case 'score-asc':   return getPageScore(a) - getPageScore(b);
                case 'score-desc':  return getPageScore(b) - getPageScore(a);
                case 'issues-desc': return getPageIssueCount(b) - getPageIssueCount(a);
                case 'words-desc':  return (b.wordCount || 0) - (a.wordCount || 0);
                case 'words-asc':   return (a.wordCount || 0) - (b.wordCount || 0);
                case 'links-desc':  return (inLinks[b.url] || 0) - (inLinks[a.url] || 0);
                case 'url-asc':     return (a.url || '').localeCompare(b.url || '');
                default:            return getPageScore(b) - getPageScore(a);
            }
        });

        return { filtered: filtered, inLinks: inLinks };
    }

    function renderSiteAuditStats(audit) {
        var pages = (audit && audit.pages_reviewed) || [];
        var total = pages.length;
        var scored = pages.filter(function (p) { return getPageScore(p) > 0; });
        var scores = scored.map(getPageScore);
        var avg = scores.length ? Math.round(scores.reduce(function (a, b) { return a + b; }, 0) / scores.length) : 0;
        var strong = scores.filter(function (s) { return s >= 70; }).length;
        var needs  = scores.filter(function (s) { return s > 0 && s < 70; }).length;

        return ''
            + '<div class="aeo-log-stats" id="aeo-site-audit-stats">'
            +   '<div class="aeo-stat-card"><span class="aeo-stat-number">' + total + '</span><span class="aeo-stat-label">Total Pages</span></div>'
            +   '<div class="aeo-stat-card"><span class="aeo-stat-number">' + avg + '</span><span class="aeo-stat-label">Avg AEO Rank</span></div>'
            +   '<div class="aeo-stat-card"><span class="aeo-stat-number aeo-stat-success">' + strong + '</span><span class="aeo-stat-label">Strong (70+)</span></div>'
            +   '<div class="aeo-stat-card"><span class="aeo-stat-number" style="color:#ea4335;">' + needs + '</span><span class="aeo-stat-label">Needs Attention</span></div>'
            + '</div>';
    }

    function renderSiteAuditToolbar(audit) {
        var cats = collectPageCategories(audit.pages_reviewed || []);
        var f = siteAuditFilters;
        var catOptions = '<option value="all"' + (f.category === 'all' ? ' selected' : '') + '>All categories</option>';
        cats.forEach(function (c) {
            catOptions += '<option value="' + esc(c) + '"' + (f.category === c ? ' selected' : '') + '>' + esc(c) + '</option>';
        });

        function opt(val, label, current) {
            return '<option value="' + val + '"' + (current === val ? ' selected' : '') + '>' + esc(label) + '</option>';
        }

        var rangeOptions = ''
            + opt('all',      'All scores',           f.scoreRange)
            + opt('high',     'Strong (70+)',         f.scoreRange)
            + opt('mid',      'Moderate (40–69)',     f.scoreRange)
            + opt('low',      'Weak (1–39)',          f.scoreRange)
            + opt('unscored', 'Unscored',             f.scoreRange);

        var sortOptions = ''
            + opt('score-desc',  'AEO rank (high → low)', f.sort)
            + opt('score-asc',   'AEO rank (low → high)', f.sort)
            + opt('issues-desc', 'Issues (most first)',   f.sort)
            + opt('words-desc',  'Word count (high → low)', f.sort)
            + opt('words-asc',   'Word count (low → high)', f.sort)
            + opt('links-desc',  'Inbound links (most first)', f.sort)
            + opt('url-asc',     'URL (A → Z)', f.sort);

        return ''
            + '<div class="aeo-site-audit-toolbar">'
            +   '<input type="search" id="aeo-site-audit-search" class="aeo-site-audit-search" placeholder="Search pages by URL or title..." value="' + esc(f.search) + '" />'
            +   '<select data-aeo-filter="category" class="aeo-site-audit-select">' + catOptions + '</select>'
            +   '<select data-aeo-filter="scoreRange" class="aeo-site-audit-select">' + rangeOptions + '</select>'
            +   '<select data-aeo-filter="sort" class="aeo-site-audit-select">' + sortOptions + '</select>'
            +   '<span class="aeo-site-audit-count" id="aeo-site-audit-count"></span>'
            + '</div>';
    }

    function renderSiteAuditRows(audit) {
        var out = applySiteAuditFilters(audit);
        var rows = out.filtered;
        var inLinks = out.inLinks;

        if (!rows.length) {
            return '<tr><td colspan="5" class="aeo-site-audit-empty">No pages match the current filters.</td></tr>';
        }

        return rows.map(function (page) {
            var score = getPageScore(page);
            var color = scoreColor100(score);
            var bg    = scoreBg100(score);
            var cat   = page.category || '';
            var words = page.wordCount || 0;
            var il    = inLinks[page.url] || 0;
            var shortUrl = (page.url || '').replace(/^https?:\/\/[^/]+/, '');
            var issueCount = getPageIssueCount(page);
            var issueHtml = issueCount > 0
                ? '<span class="aeo-site-audit-issues">' + issueCount + ' issue' + (issueCount === 1 ? '' : 's') + '</span>'
                : '';

            return ''
                + '<tr>'
                +   '<td>'
                +     '<div class="aeo-site-audit-title">' + esc(page.title || shortUrl) + '</div>'
                +     '<div class="aeo-site-audit-url"><a href="' + esc(page.url) + '" target="_blank" rel="noopener">' + esc(shortUrl || page.url) + ' &#8599;</a></div>'
                +     (issueHtml ? '<div class="aeo-site-audit-issue-row">' + issueHtml + '</div>' : '')
                +   '</td>'
                +   '<td><span class="aeo-log-command">' + esc(cat) + '</span></td>'
                +   '<td><span class="aeo-score-badge-pill" style="background:' + bg + ';color:' + color + ';">' + (score > 0 ? score : '—') + '</span></td>'
                +   '<td style="color:#646970;">' + (words > 0 ? words.toLocaleString() : '—') + '</td>'
                +   '<td style="color:#646970;">' + il + '</td>'
                + '</tr>';
        }).join('');
    }

    function renderSiteAudit(audit) {
        // In-progress or no data yet: show the progress card driven by the
        // shared Discovery polling state.
        var pages = (audit && audit.pages_reviewed) || [];
        if (!pages.length) {
            return renderSiteAuditPending();
        }

        return ''
            + '<div class="aeo-site-audit-header">'
            +   '<h2>Site Audit</h2>'
            +   '<p class="description">Every page the platform crawled, scored against the full AEO criteria set.</p>'
            + '</div>'
            + renderSiteAuditStats(audit)
            + renderSiteAuditToolbar(audit)
            + '<table class="widefat fixed striped aeo-site-audit-table" id="aeo-site-audit-table">'
            +   '<thead><tr>'
            +     '<th>Page</th>'
            +     '<th style="width:120px;">Type</th>'
            +     '<th style="width:110px;">AEO Rank</th>'
            +     '<th style="width:90px;">Words</th>'
            +     '<th style="width:90px;">In Links</th>'
            +   '</tr></thead>'
            +   '<tbody id="aeo-site-audit-tbody">' + renderSiteAuditRows(audit) + '</tbody>'
            + '</table>';
    }

    function refreshSiteAuditCount() {
        if (!currentAuditData) return;
        var count = document.getElementById('aeo-site-audit-count');
        if (!count) return;
        var pages = (currentAuditData.pages_reviewed || []).length;
        var out = applySiteAuditFilters(currentAuditData);
        count.textContent = out.filtered.length + ' of ' + pages + ' pages';
    }

    function refreshSiteAuditTableOnly() {
        if (!currentAuditData) return;
        var tbody = document.getElementById('aeo-site-audit-tbody');
        if (!tbody) return;
        tbody.innerHTML = renderSiteAuditRows(currentAuditData);
        refreshSiteAuditCount();
    }

    function renderSiteAuditPending() {
        // Mirrors the Discovery pending card but worded for the full audit.
        var st = discoveryUiState;
        var status = st.status || 'pending';
        var stage  = st.currentStage || STAGE_LABELS[status] || 'Waiting for the audit worker...';
        var pct    = STAGE_PROGRESS[status] || 5;
        return ''
            + '<div class="aeo-discovery-pending aeo-site-audit-pending">'
            +   '<h2>Site audit is running…</h2>'
            +   '<p class="description">We\'re crawling every page on your site and scoring it against the full AEO criteria set. This page will update automatically as soon as results are in.</p>'
            +   '<div class="aeo-reaudit-track">'
            +     '<div class="aeo-reaudit-fill aeo-disc-fill" style="width:' + pct + '%;"></div>'
            +   '</div>'
            +   '<p class="aeo-disc-stage-row">'
            +     '<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span>'
            +     '<span>' + esc(stage) + '</span>'
            +   '</p>'
            +   '<p style="text-align:center;margin-top:14px;">'
            +     '<a href="#" class="button aeo-trigger-reaudit">Re-run Site Audit</a>'
            +   '</p>'
            + '</div>';
    }

    /* ── Render Rewrite Candidates Tab ────────────────── */

    function renderRewriteCandidates(audit) {
        var pages = audit.pages_reviewed || [];
        if (!pages.length) {
            return '<div class="aeo-log-empty">' +
                '<p style="margin-bottom:12px;">No page data available yet. Run a full site audit to see rewrite candidates.</p>' +
                '<button type="button" class="button button-primary button-hero aeo-trigger-reaudit">Run Full Site Audit</button>' +
                '</div>';
        }

        // Build link graph lookup
        var linkGraph = audit.link_graph || {};
        var nodes = linkGraph.nodes || [];
        var inLinksMap = {};
        nodes.forEach(function (n) { inLinksMap[n.url] = n.inDegree || 0; });

        // Filter to pages scoring below 70
        var candidates = pages.filter(function (p) {
            var score = p.pageRankScore || (p.pageRank ? p.pageRank.score : 0);
            return score > 0 && score < 70;
        }).map(function (p) {
            var score = p.pageRankScore || (p.pageRank ? p.pageRank.score : 0);
            var tier = score < 40 ? 'high' : score < 55 ? 'medium' : 'low';
            var inLinks = inLinksMap[p.url] || 0;

            // Find weakest pillar
            var weakest = null;
            if (p.pillarScores) {
                var pillars = [
                    { name: 'Answer Readiness', score: p.pillarScores.answerReadiness || 0 },
                    { name: 'Content Structure', score: p.pillarScores.contentStructure || 0 },
                    { name: 'Trust & Authority', score: p.pillarScores.trustAuthority || 0 },
                    { name: 'Technical Foundation', score: p.pillarScores.technicalFoundation || 0 },
                    { name: 'AI Discovery', score: p.pillarScores.aiDiscovery || 0 },
                ];
                pillars.sort(function (a, b) { return a.score - b.score; });
                weakest = pillars[0];
            }

            // Priority score: lower AEO score + more inlinks = higher priority
            var priority = (100 - score) + Math.min(inLinks * 5, 50);

            return {
                url: p.url,
                title: p.title || p.url,
                category: p.category || '',
                score: score,
                tier: tier,
                inLinks: inLinks,
                words: p.wordCount || 0,
                weakest: weakest,
                priority: priority,
                topFixes: p.topFixes || [],
                isStale: p.issues ? p.issues.some(function (i) { return (i.check || '').indexOf('freshness') !== -1; }) : false,
            };
        });

        // Sort by priority descending
        candidates.sort(function (a, b) { return b.priority - a.priority; });

        var highCount = candidates.filter(function (c) { return c.tier === 'high'; }).length;
        var medCount = candidates.filter(function (c) { return c.tier === 'medium'; }).length;
        var lowCount = candidates.filter(function (c) { return c.tier === 'low'; }).length;

        var html = '';

        // Stats bar
        html += '<div class="aeo-log-stats" style="margin-bottom:16px;">';
        html += '<div class="aeo-stat-card"><span class="aeo-stat-number">' + candidates.length + '</span><span class="aeo-stat-label">Total Rewrites</span></div>';
        html += '<div class="aeo-stat-card"><span class="aeo-stat-number" style="color:#ea4335;">' + highCount + '</span><span class="aeo-stat-label">High Priority</span></div>';
        html += '<div class="aeo-stat-card"><span class="aeo-stat-number" style="color:#c5a200;">' + medCount + '</span><span class="aeo-stat-label">Medium Priority</span></div>';
        html += '<div class="aeo-stat-card"><span class="aeo-stat-number" style="color:#34a853;">' + lowCount + '</span><span class="aeo-stat-label">Low Priority</span></div>';
        html += '</div>';

        if (!candidates.length) {
            return html + '<div class="aeo-log-empty"><p>All scored pages are at 70 or above. No rewrites needed.</p></div>';
        }

        // Table
        html += '<table class="widefat fixed striped" style="margin-top:0;">';
        html += '<thead><tr>';
        html += '<th>Page</th>';
        html += '<th style="width:80px;">AEO Rank</th>';
        html += '<th style="width:80px;">Priority</th>';
        html += '<th style="width:150px;">Weakest Pillar</th>';
        html += '<th style="width:70px;">Words</th>';
        html += '<th style="width:70px;">In Links</th>';
        html += '</tr></thead><tbody>';

        candidates.forEach(function (c) {
            var color = scoreColor100(c.score);
            var bg = scoreBg100(c.score);
            var tierColor = c.tier === 'high' ? '#ea4335' : c.tier === 'medium' ? '#c5a200' : '#34a853';
            var tierBg = c.tier === 'high' ? 'rgba(234,67,53,0.12)' : c.tier === 'medium' ? 'rgba(197,162,0,0.12)' : 'rgba(52,168,83,0.12)';
            var shortUrl = c.url.replace(/^https?:\/\/[^/]+/, '');

            html += '<tr>';
            html += '<td>';
            html += '<div style="font-weight:600;font-size:13px;">' + esc(c.title) + '</div>';
            html += '<div style="font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:400px;"><a href="' + esc(c.url) + '" target="_blank" rel="noopener" style="color:#646970;text-decoration:none;" onmouseover="this.style.color=\'#2271b1\'" onmouseout="this.style.color=\'#646970\'">' + esc(shortUrl) + ' &#8599;</a></div>';
            if (c.category) html += ' <span class="aeo-log-command" style="margin-top:2px;">' + esc(c.category) + '</span>';
            if (c.isStale) html += ' <span class="aeo-badge aeo-badge-error" style="font-size:10px;">stale</span>';
            html += '</td>';
            html += '<td><span class="aeo-score-badge-pill" style="background:' + bg + ';color:' + color + ';">' + c.score + '</span></td>';
            html += '<td><span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;text-transform:uppercase;background:' + tierBg + ';color:' + tierColor + ';">' + c.tier + '</span></td>';
            html += '<td>';
            if (c.weakest) {
                var wColor = scoreColor100(c.weakest.score);
                html += '<span style="font-size:12px;">' + esc(c.weakest.name) + '</span>';
                html += ' <span style="font-weight:600;color:' + wColor + ';">' + c.weakest.score + '</span>';
            } else {
                html += '<span style="color:#a7aaad;">-</span>';
            }
            html += '</td>';
            html += '<td style="color:#646970;">' + (c.words > 0 ? c.words.toLocaleString() : '-') + '</td>';
            html += '<td style="color:#646970;">' + c.inLinks + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        return html;
    }

    /* ── Discovery Tab ────────────────────────────────── */

    // Tab IDs that are driven by audit JSON data (and thus get a loading /
    // empty state when no audit is loaded). Connect and Activity have their
    // own server-rendered content and are excluded.
    var AUDIT_TAB_IDS = ['site-audit', 'scoreboard', 'opportunities', 'rewrite'];
    var discoveryPollTimer = null;
    var auditRetryTimer = null;
    var currentAuditData = null;
    var siteAuditFilters = {
        category: 'all',
        scoreRange: 'all',
        sort: 'score-desc',
        search: ''
    };

    function renderDiscoveryLoading() {
        return '<div class="aeo-tab-loading"><span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>Loading discovery findings...</div>';
    }

    function renderAuditLoading() {
        return '<div class="aeo-tab-loading"><span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>Loading audit data...</div>';
    }

    function renderAuditEmpty(message) {
        return ''
            + '<div class="aeo-tab-empty">'
            +   '<p>' + esc(message || 'No audit data yet.') + '</p>'
            +   '<p><a href="#" class="button button-primary aeo-trigger-reaudit">Run Full Site Audit</a></p>'
            + '</div>';
    }

    function setAuditTabsLoading() {
        AUDIT_TAB_IDS.forEach(function (id) {
            var el = document.getElementById('tab-' + id);
            if (el) el.innerHTML = renderAuditLoading();
        });
    }

    function setAuditTabsEmpty(message) {
        AUDIT_TAB_IDS.forEach(function (id) {
            var el = document.getElementById('tab-' + id);
            if (el) el.innerHTML = renderAuditEmpty(message);
        });
    }

    function firstNonEmpty() {
        for (var i = 0; i < arguments.length; i++) {
            var v = arguments[i];
            if (v === undefined || v === null) continue;
            if (typeof v === 'string' && v.trim() === '') continue;
            if (Array.isArray(v) && v.length === 0) continue;
            return v;
        }
        return null;
    }

    function mergeArrays() {
        var seen = {};
        var out = [];
        for (var i = 0; i < arguments.length; i++) {
            var arr = arguments[i];
            if (!Array.isArray(arr)) continue;
            for (var j = 0; j < arr.length; j++) {
                var v = arr[j];
                if (v == null) continue;
                var key = typeof v === 'string' ? v.toLowerCase().trim() : JSON.stringify(v);
                if (!key || seen[key]) continue;
                seen[key] = true;
                out.push(v);
            }
        }
        return out;
    }

    function fieldRow(label, value) {
        if (value === undefined || value === null || value === '') return '';
        return ''
            + '<div class="aeo-discovery-field">'
            +   '<div class="aeo-discovery-label">' + esc(label) + '</div>'
            +   '<div class="aeo-discovery-value">' + esc(String(value)) + '</div>'
            + '</div>';
    }

    function chipList(label, items) {
        if (!items || !items.length) return '';
        var chips = items.map(function (item) {
            return '<span class="aeo-discovery-chip">' + esc(String(item)) + '</span>';
        }).join('');
        return ''
            + '<div class="aeo-discovery-field">'
            +   '<div class="aeo-discovery-label">' + esc(label) + '</div>'
            +   '<div class="aeo-discovery-chips">' + chips + '</div>'
            + '</div>';
    }

    function bulletList(label, items) {
        if (!items || !items.length) return '';
        var lis = items.map(function (item) {
            return '<li>' + esc(String(item)) + '</li>';
        }).join('');
        return ''
            + '<div class="aeo-discovery-field">'
            +   '<div class="aeo-discovery-label">' + esc(label) + '</div>'
            +   '<ul class="aeo-discovery-bullets">' + lis + '</ul>'
            + '</div>';
    }

    function card(title, inner) {
        if (!inner || !inner.trim()) return '';
        return ''
            + '<section class="aeo-discovery-card">'
            +   '<h3 class="aeo-discovery-card-title">' + esc(title) + '</h3>'
            +   inner
            + '</section>';
    }

    function formatDate(iso) {
        if (!iso) return '';
        var d = new Date(iso);
        if (isNaN(d.getTime())) return String(iso);
        try {
            return d.toLocaleString();
        } catch (e) {
            return d.toISOString();
        }
    }

    function renderIdentityCard(d, dp) {
        var inner = ''
            + fieldRow('Domain', firstNonEmpty(d.target_domain, dp.company_name))
            + fieldRow('Business name', firstNonEmpty(d.business_name, dp.company_name))
            + fieldRow('Niche', d.niche)
            + fieldRow('Search term', d.search_term)
            + fieldRow('Meta description', dp.meta_description)
            + fieldRow('Business description', d.business_description);
        return card('Identity', inner);
    }

    function renderOfferingCard(d, dp) {
        var services = mergeArrays(d.services, d.priority_services, dp.services);
        var inner = ''
            + fieldRow('Value proposition', firstNonEmpty(d.value_proposition, dp.value_proposition))
            + chipList('Services', services)
            + chipList('Differentiators', d.differentiators || [])
            + chipList('Content themes', d.content_themes || [])
            + chipList('Primary CTAs', mergeArrays(d.primary_ctas, dp.primary_ctas));
        return card('Offering', inner);
    }

    function renderAudienceCard(d, dp) {
        var inner = ''
            + fieldRow('Target audience', d.target_audience)
            + bulletList('Pain points', mergeArrays(d.pain_points, dp.pain_points))
            + bulletList('Objections', d.objections || [])
            + bulletList('Proof points', mergeArrays((d.proof_points || []).map(function (p) {
                if (typeof p === 'string') return p;
                return p && (p.claim || p.text || p.name) ? (p.claim || p.text || p.name) : JSON.stringify(p);
            }), dp.proof_points))
            + chipList('Preferred terms', d.preferred_terms || [])
            + chipList('Avoid terms', d.avoid_terms || []);
        return card('Audience & Positioning', inner);
    }

    function renderContentSignalsCard(d, dp) {
        var inner = ''
            + chipList('Topic phrases', mergeArrays(d.topic_phrases, dp.topic_phrases))
            + chipList('Entities', mergeArrays(d.entities, dp.entities))
            + chipList('Content gaps', mergeArrays(d.content_gaps, dp.content_gaps))
            + chipList('Content formats', dp.content_formats || [])
            + bulletList('FAQ questions', dp.faq_questions || [])
            + bulletList('Page titles (sample)', (dp.page_titles || []).slice(0, 20))
            + bulletList('Headings (sample)', (dp.headings || []).slice(0, 20));
        return card('Content Signals', inner);
    }

    function renderSiteStructureCard(d, dp) {
        var inner = ''
            + fieldRow('Sitemap URL count', dp.sitemap_url_count)
            + fieldRow('Blog post count', dp.blog_post_count)
            + fieldRow('Publishing frequency', dp.publishing_frequency)
            + chipList('Nav links', (dp.nav_links || []).slice(0, 30));
        return card('Site Structure', inner);
    }

    function renderVoiceCard(d, dp) {
        var vp = d.voice_profile || {};
        var vs = dp.voice_signals || {};
        var inner = ''
            + fieldRow('Person', firstNonEmpty(vp.person, vs.person))
            + fieldRow('Formality', firstNonEmpty(vp.formality, vs.formality))
            + fieldRow('Tone', vp.tone)
            + fieldRow('Avg sentence length', vs.avg_sentence_length)
            + fieldRow('Contraction rate', vs.contraction_rate);
        return card('Voice & Style', inner);
    }

    function renderCompetitorsCard(d) {
        var comps = d.competitors || [];
        if (!comps.length) return '';
        var rows = comps.map(function (c) {
            return ''
                + '<li class="aeo-discovery-competitor">'
                +   '<div class="aeo-discovery-competitor-name"><strong>' + esc(c.name || c.domain || '') + '</strong>'
                +     (c.domain ? ' <span class="aeo-discovery-competitor-domain">' + esc(c.domain) + '</span>' : '')
                +   '</div>'
                +   (c.why ? '<div class="aeo-discovery-competitor-why">' + esc(c.why) + '</div>' : '')
                + '</li>';
        }).join('');
        return card('Competitors', '<ul class="aeo-discovery-competitors">' + rows + '</ul>');
    }

    function renderQueriesCard(d) {
        var inner = ''
            + bulletList('Solution queries', d.solution_queries || [])
            + bulletList('Excluded queries', d.excluded_queries || [])
            + bulletList('Compliance constraints', d.compliance_constraints || []);
        var clusters = d.intent_clusters || [];
        if (clusters.length) {
            var rows = clusters.map(function (c) {
                var qs = (c.queries || c.examples || []).map(function (q) { return '<li>' + esc(String(q)) + '</li>'; }).join('');
                return ''
                    + '<div class="aeo-discovery-cluster">'
                    +   '<div class="aeo-discovery-label">' + esc(c.name || c.intent || 'Cluster') + '</div>'
                    +   (qs ? '<ul class="aeo-discovery-bullets">' + qs + '</ul>' : '')
                    + '</div>';
            }).join('');
            inner += '<div class="aeo-discovery-field"><div class="aeo-discovery-label">Intent clusters</div>' + rows + '</div>';
        }
        return card('Search Intent', inner);
    }

    /* Rotating wait verbs, displayed while Discovery is pending.  */
    var DISCOVERY_VERBS = [
        'Pondering', 'Synthesizing', 'Contemplating', 'Ruminating',
        'Deliberating', 'Cogitating', 'Mulling', 'Percolating',
        'Marinating', 'Brewing', 'Noodling', 'Untangling',
        'Crunching numbers', 'Spelunking', 'Rummaging', 'Sniffing around',
        'Harvesting signals', 'Wrangling bytes', 'Chasing breadcrumbs',
        'Scouting the terrain', 'Decoding', 'Weaving', 'Parsing vibes',
        'Reticulating splines', 'Consulting the oracle', 'Sharpening pencils',
        'Reading tea leaves', 'Herding electrons', 'Counting pixels',
        'Triangulating', 'Whispering to the DB', 'Polishing the scoreboard'
    ];

    var discoveryUiState = {
        phase: 'idle',     // 'idle' | 'loading' | 'pending' | 'ready' | 'error'
        startedAt: null,
        lastPollAt: null,
        verbIdx: 0,
        tickCounter: 0,
        tickTimer: null,
        status: null,
        currentStage: null
    };

    function formatElapsed(ms) {
        var total = Math.max(0, Math.floor(ms / 1000));
        var m = Math.floor(total / 60);
        var s = total % 60;
        if (m === 0) return s + 's';
        return m + 'm ' + (s < 10 ? '0' : '') + s + 's';
    }

    function updateDiscoveryPendingDynamic() {
        var st = discoveryUiState;
        if (st.phase !== 'pending') return;

        var verbEl    = document.getElementById('aeo-disc-verb');
        var stageEl   = document.getElementById('aeo-disc-stage');
        var elapsedEl = document.getElementById('aeo-disc-elapsed');
        var lastPollEl= document.getElementById('aeo-disc-lastpoll');
        var fillEl    = document.getElementById('aeo-disc-fill');

        if (!verbEl && !stageEl && !elapsedEl && !lastPollEl) return; // DOM gone

        if (verbEl) {
            verbEl.textContent = DISCOVERY_VERBS[st.verbIdx % DISCOVERY_VERBS.length] + '…';
        }
        if (stageEl) {
            var label = st.currentStage || STAGE_LABELS[st.status] || 'Waiting for the audit worker to pick up your job...';
            stageEl.textContent = label;
        }
        if (elapsedEl && st.startedAt) {
            elapsedEl.textContent = formatElapsed(Date.now() - st.startedAt);
        }
        if (lastPollEl && st.lastPollAt) {
            var secs = Math.max(0, Math.round((Date.now() - st.lastPollAt) / 1000));
            lastPollEl.textContent = secs === 0 ? 'just now' : (secs + 's ago');
        }
        if (fillEl) {
            var pct = STAGE_PROGRESS[st.status] || 5;
            fillEl.style.width = pct + '%';
        }
    }

    function startDiscoveryTicker() {
        stopDiscoveryTicker();
        discoveryUiState.tickTimer = setInterval(function () {
            discoveryUiState.tickCounter++;
            // Rotate the verb every 6 ticks (~6s) — slow enough to read each
            // word comfortably without feeling static.
            if (discoveryUiState.tickCounter % 6 === 0) {
                discoveryUiState.verbIdx++;
            }
            updateDiscoveryPendingDynamic();
        }, 1000);
    }

    function stopDiscoveryTicker() {
        if (discoveryUiState.tickTimer) {
            clearInterval(discoveryUiState.tickTimer);
            discoveryUiState.tickTimer = null;
        }
    }

    function renderDiscoveryPending(payload) {
        // Capture state for the ticker. If we're already in a pending phase,
        // preserve startedAt so the elapsed counter keeps climbing across
        // re-renders; otherwise start a fresh timer.
        var st = discoveryUiState;
        if (st.phase !== 'pending') {
            st.startedAt = Date.now();
            st.verbIdx = 0;
            st.tickCounter = 0;
        }
        st.phase         = 'pending';
        st.status        = (payload && payload.status) || 'pending';
        st.currentStage  = (payload && payload.current_stage) || null;
        st.lastPollAt    = Date.now();

        var stageLabel = st.currentStage || STAGE_LABELS[st.status] || 'Waiting for the audit worker to pick up your job...';
        var pct        = STAGE_PROGRESS[st.status] || 5;
        var verb       = DISCOVERY_VERBS[st.verbIdx % DISCOVERY_VERBS.length];

        return ''
            + '<div class="aeo-discovery-pending">'
            +   '<h2>Discovery is running…</h2>'
            +   '<p class="description">We kicked off a full site audit the moment you connected. The deterministic Discovery layer usually finishes in under a minute and its findings appear here automatically.</p>'
            +   '<p class="aeo-disc-verb-row">'
            +     '<span class="aeo-disc-verb" id="aeo-disc-verb">' + esc(verb) + '…</span>'
            +   '</p>'
            +   '<div class="aeo-reaudit-track">'
            +     '<div class="aeo-reaudit-fill aeo-disc-fill" id="aeo-disc-fill" style="width:' + pct + '%;"></div>'
            +   '</div>'
            +   '<p class="aeo-disc-stage-row">'
            +     '<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span>'
            +     '<span id="aeo-disc-stage">' + esc(stageLabel) + '</span>'
            +   '</p>'
            +   '<div class="aeo-disc-meta">'
            +     '<span>Running for <strong id="aeo-disc-elapsed">' + esc(formatElapsed(Date.now() - st.startedAt)) + '</strong></span>'
            +     '<span>Last checked <strong id="aeo-disc-lastpoll">just now</strong></span>'
            +   '</div>'
            + '</div>';
    }

    function renderDiscoveryFailed(payload) {
        var stage = (payload && payload.current_stage) || 'Audit failed.';
        return ''
            + '<div class="aeo-discovery-pending aeo-discovery-failed">'
            +   '<h2>Audit failed</h2>'
            +   '<p class="description">The platform ran into an error while processing your site. You can retry the audit — if it keeps failing, please contact support.</p>'
            +   '<p class="aeo-disc-stage-row" style="justify-content:center;">'
            +     '<span class="dashicons dashicons-warning" style="color:#ea4335;margin-right:6px;"></span>'
            +     '<span>' + esc(stage) + '</span>'
            +   '</p>'
            +   '<p style="text-align:center;margin-top:16px;">'
            +     '<a href="#" class="button button-primary aeo-trigger-reaudit">Retry Audit</a>'
            +   '</p>'
            + '</div>';
    }

    function renderDiscovery(payload) {
        if (!payload || !payload.discovery) {
            return renderDiscoveryPending(payload);
        }
        var d  = payload.discovery || {};
        var dp = d.deterministic_profile || {};

        var extracted = firstNonEmpty(d.topics_extracted_at, dp.extracted_at);
        var meta = ''
            + '<div class="aeo-discovery-meta">'
            +   '<span><strong>Status:</strong> ' + esc(payload.status || '—') + '</span>'
            +   (extracted ? '<span><strong>Extracted:</strong> ' + esc(formatDate(extracted)) + '</span>' : '')
            + '</div>';

        var cards = ''
            + renderIdentityCard(d, dp)
            + renderOfferingCard(d, dp)
            + renderAudienceCard(d, dp)
            + renderContentSignalsCard(d, dp)
            + renderSiteStructureCard(d, dp)
            + renderVoiceCard(d, dp)
            + renderCompetitorsCard(d)
            + renderQueriesCard(d);

        return ''
            + '<div class="aeo-discovery-header">'
            +   '<h2>Discovery findings</h2>'
            +   '<p class="description">Everything the platform extracted about your site during the deterministic Discovery stage.</p>'
            +   meta
            + '</div>'
            + '<div class="aeo-discovery-grid">' + cards + '</div>';
    }

    /* ── Render All ───────────────────────────────────── */

    function renderAudit(audit) {
        currentAuditData = audit;
        document.getElementById('tab-scoreboard').innerHTML    = renderScoreboard(audit);
        document.getElementById('tab-opportunities').innerHTML = renderOpportunities(audit);
        document.getElementById('tab-site-audit').innerHTML    = renderSiteAudit(audit);
        document.getElementById('tab-rewrite').innerHTML       = renderRewriteCandidates(audit);
        // The Connect tab has server-rendered settings content; the audit
        // overview is appended below it in a dedicated child div.
        var connectAudit = document.getElementById('aeo-connect-audit-section');
        if (connectAudit) {
            connectAudit.innerHTML = '<div class="aeo-connect-audit-header"><h2>Your Latest Audit</h2></div>' + renderOverview(audit);
            connectAudit.style.display = '';
        }
        refreshSiteAuditCount();
    }

    /* ── Score Breakdown Modal ────────────────────────── */

    var SCORE_RANGES = [
        { min: 86, max: 100, label: 'AI-first content architecture',  color: '#34a853' },
        { min: 71, max: 85,  label: 'Strong AI visibility',            color: '#34a853' },
        { min: 56, max: 70,  label: 'Moderate visibility',             color: '#c5a200' },
        { min: 41, max: 55,  label: 'Weak visibility',                 color: '#c5a200' },
        { min: 26, max: 40,  label: 'Minimal visibility',              color: '#ea4335' },
        { min: 0,  max: 25,  label: 'Not on the radar',                color: '#ea4335' }
    ];

    function getScoreRangeLabel(score) {
        for (var i = 0; i < SCORE_RANGES.length; i++) {
            if (score >= SCORE_RANGES[i].min && score <= SCORE_RANGES[i].max) {
                return SCORE_RANGES[i];
            }
        }
        return SCORE_RANGES[SCORE_RANGES.length - 1];
    }

    function renderScoreBreakdown(audit) {
        if (!audit) return '<p>No audit data loaded yet.</p>';
        var scorecard = audit.scorecard || [];
        var cats = getCategories(scorecard);
        var overall = audit.overall_score || 0;
        var range = getScoreRangeLabel(overall);

        // Build map of criterion id → item
        var byId = {};
        scorecard.forEach(function (item) { byId[item.id] = item; });

        var html = '';

        // Header: overall score + human-readable range
        html += '<div class="aeo-score-modal-hero">';
        html +=   '<div class="aeo-score-modal-score">';
        html +=     renderScoreCircle(overall, 100, 120);
        html +=   '</div>';
        html +=   '<div class="aeo-score-modal-range">';
        html +=     '<div class="aeo-score-modal-range-label" style="color:' + range.color + ';">' + esc(range.label) + '</div>';
        html +=     '<p class="description">Your AEO Page Rank is a weighted composite across ' + cats.length + ' pillars. Each pillar contains multiple criteria scored 0–10; the pillar score is the average of its criteria, and the overall score is the weighted sum of the pillar scores (×10).</p>';
        html +=   '</div>';
        html += '</div>';

        // Pillar cards
        html += '<h3 class="aeo-score-modal-subhead">Pillar breakdown</h3>';
        html += '<div class="aeo-score-modal-pillars">';
        cats.forEach(function (cat) {
            var items = (cat.ids || []).map(function (id) { return byId[id]; }).filter(Boolean);
            if (!items.length) return;
            var avg = items.reduce(function (a, b) { return a + (b.score || 0); }, 0) / items.length;
            var avgRounded = Math.round(avg * 10) / 10;
            var pct100 = Math.round(avg * 10);

            html += '<section class="aeo-score-pillar-card">';
            html +=   '<div class="aeo-score-pillar-head">';
            html +=     '<span class="aeo-cat-badge" style="background:' + cat.bg + ';color:' + cat.color + ';">' + esc(cat.label).toUpperCase() + '</span>';
            html +=     (cat.weight ? '<span class="aeo-score-pillar-weight">' + esc(cat.weight) + ' of overall</span>' : '');
            html +=     '<span class="aeo-score-pillar-avg" style="color:' + scoreColor100(pct100) + ';">' + avgRounded + '/10</span>';
            html +=   '</div>';
            html +=   '<div class="aeo-score-pillar-bar"><div class="aeo-score-pillar-fill" style="width:' + pct100 + '%;background:' + scoreColor100(pct100) + ';"></div></div>';
            html +=   '<ul class="aeo-score-pillar-criteria">';
            items.forEach(function (item) {
                var clr = scoreColor10(item.score);
                var bg  = scoreBg10(item.score);
                html += '<li>';
                html +=   '<span class="aeo-score-pillar-crit-name">' + esc(item.criterion) + '</span>';
                html +=   '<span class="aeo-score-badge-pill" style="background:' + bg + ';color:' + clr + ';">' + (item.score || 0) + '/10</span>';
                html += '</li>';
            });
            html +=   '</ul>';
            html += '</section>';
        });
        html += '</div>';

        // Score ranges reference table
        html += '<h3 class="aeo-score-modal-subhead">Score ranges</h3>';
        html += '<table class="widefat aeo-score-ranges-table">';
        html +=   '<thead><tr><th>Range</th><th>Label</th><th>What it means</th></tr></thead><tbody>';
        var descriptions = [
            'Reference-grade content. AI assistants cite pages here verbatim.',
            'Competitive presence in AI answers across your core queries.',
            'Inconsistent visibility; some queries surface you, most don\'t.',
            'Thin presence; AI assistants rarely cite you.',
            'You show up only for long-tail or brand-exact queries.',
            'No meaningful AI visibility yet.'
        ];
        SCORE_RANGES.forEach(function (r, i) {
            var highlight = overall >= r.min && overall <= r.max ? ' style="background:' + r.color + '22;"' : '';
            html += '<tr' + highlight + '>';
            html += '<td><strong>' + r.min + '–' + r.max + '</strong></td>';
            html += '<td><span style="color:' + r.color + ';font-weight:600;">' + esc(r.label) + '</span></td>';
            html += '<td style="color:#50575e;">' + esc(descriptions[i]) + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table>';

        return html;
    }

    function openScoreModal() {
        if (!currentAuditData) return;
        var modal = document.getElementById('aeo-score-modal');
        var body  = document.getElementById('aeo-score-modal-body');
        if (!modal || !body) return;
        body.innerHTML = renderScoreBreakdown(currentAuditData);
        modal.style.display = '';
        document.body.style.overflow = 'hidden';
    }

    function closeScoreModal() {
        var modal = document.getElementById('aeo-score-modal');
        if (!modal) return;
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    /* ── Load Audit Data ──────────────────────────────── */

    function stopAuditRetry() {
        if (auditRetryTimer) {
            clearInterval(auditRetryTimer);
            auditRetryTimer = null;
        }
    }

    function startAuditRetry() {
        stopAuditRetry();
        // Re-fetch the audit every 15s while we still don't have data. The
        // Site Audit tab shows a live progress card driven by discoveryUiState
        // between polls, so the user sees motion even during the wait.
        auditRetryTimer = setInterval(function () {
            if (currentAuditData) { stopAuditRetry(); return; }
            loadAudit(true);
        }, 15000);
    }

    function setSiteAuditPending() {
        var tab = document.getElementById('tab-site-audit');
        if (tab) tab.innerHTML = renderSiteAuditPending();
    }

    function loadAudit(refresh) {
        if (!currentAuditData) {
            setAuditTabsLoading();
            setSiteAuditPending();
        }
        errorBox.innerHTML = '';

        var data = new FormData();
        data.append('action', 'aeocas_get_audit');
        data.append('nonce', aeocasAudit.nonce);
        if (refresh) data.append('refresh', '1');

        fetch(aeocasAudit.ajaxUrl, { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    stopAuditRetry();
                    renderAudit(res.data);
                } else {
                    var msg = 'Your first site audit is still running.';
                    if (res.data) {
                        if (typeof res.data === 'string') msg = res.data;
                        else if (res.data.message) msg = res.data.message;
                    }
                    // Other audit tabs (Overview/Scoreboard/Opportunities/Rewrite)
                    // show the simple empty state, but Site Audit shows the
                    // live progress card so users watch it populate.
                    AUDIT_TAB_IDS.forEach(function (id) {
                        var el = document.getElementById('tab-' + id);
                        if (!el) return;
                        if (id === 'site-audit') {
                            el.innerHTML = renderSiteAuditPending();
                        } else {
                            el.innerHTML = renderAuditEmpty(msg);
                        }
                    });
                    startAuditRetry();
                }
            })
            .catch(function (err) {
                AUDIT_TAB_IDS.forEach(function (id) {
                    var el = document.getElementById('tab-' + id);
                    if (!el) return;
                    if (id === 'site-audit') {
                        el.innerHTML = renderSiteAuditPending();
                    } else {
                        el.innerHTML = renderAuditEmpty('Network error: ' + (err.message || 'Please try again.'));
                    }
                });
                startAuditRetry();
            });
    }

    /* ── Load Discovery Data ──────────────────────────── */

    function stopDiscoveryPolling() {
        if (discoveryPollTimer) {
            clearInterval(discoveryPollTimer);
            discoveryPollTimer = null;
        }
    }

    function startDiscoveryPolling() {
        stopDiscoveryPolling();
        // 5s interval — fast enough to feel live but gentle on the remote.
        discoveryPollTimer = setInterval(function () { loadDiscovery(true); }, 5000);
    }

    function renderPendingFresh(tab, payload) {
        tab.innerHTML = renderDiscoveryPending(payload);
        startDiscoveryTicker();
    }

    function applyPendingUpdate(payload) {
        // Already showing the pending card — just patch the dynamic state and let
        // the ticker update the DOM. Avoids a full innerHTML replacement flash
        // and keeps the elapsed counter climbing smoothly.
        var st = discoveryUiState;
        st.status       = (payload && payload.status) || 'pending';
        st.currentStage = (payload && payload.current_stage) || null;
        st.lastPollAt   = Date.now();
        updateDiscoveryPendingDynamic();
        // The Site Audit pending card mirrors discoveryUiState, so re-render
        // it if the audit data isn't in yet.
        if (!currentAuditData) setSiteAuditPending();
    }

    function loadDiscovery(refresh) {
        var tab = document.getElementById('tab-discovery');
        if (!tab) return;

        // First paint: show a loading spinner until the first response lands.
        if (!refresh && !tab.innerHTML.trim()) {
            discoveryUiState.phase = 'loading';
            tab.innerHTML = renderDiscoveryLoading();
        }

        var data = new FormData();
        data.append('action', 'aeocas_get_discovery');
        data.append('nonce', aeocasAudit.nonce);
        if (refresh) data.append('refresh', '1');

        fetch(aeocasAudit.ajaxUrl, { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    var payload = res.data;
                    // If the remote job has reached completed, also refresh the
                    // audit endpoint so the Site Audit tab (and friends) can
                    // render the pages list as soon as it's available.
                    if (payload && payload.status === 'completed' && !currentAuditData) {
                        loadAudit(true);
                    }
                    if (payload && payload.status === 'failed') {
                        // Audit failed on the platform side. Stop everything
                        // and show a clear failure card with a retry button.
                        stopDiscoveryPolling();
                        stopDiscoveryTicker();
                        discoveryUiState.phase = 'error';
                        tab.innerHTML = renderDiscoveryFailed(payload);
                        setSiteAuditPending(); // will also show failed state via shared stage label
                    } else if (payload && payload.discovery) {
                        // Ready: stop everything and render full findings.
                        stopDiscoveryPolling();
                        stopDiscoveryTicker();
                        discoveryUiState.phase = 'ready';
                        tab.innerHTML = renderDiscovery(payload);
                    } else if (discoveryUiState.phase === 'pending' && document.getElementById('aeo-disc-verb')) {
                        // Already in pending state with live DOM — patch in place.
                        applyPendingUpdate(payload);
                        startDiscoveryPolling();
                    } else {
                        // First pending render (or recovering from error/loading).
                        renderPendingFresh(tab, payload);
                        startDiscoveryPolling();
                    }
                } else {
                    var msg = (res.data && res.data.message) ? res.data.message : 'Failed to load discovery.';
                    var code = (res.data && res.data.code) || '';
                    if (code === 'aeocas_no_discovery') {
                        // No job row yet on the remote — probably the onboard insert
                        // hasn't landed yet, or the user arrived before connecting.
                        // Render the pending card and KEEP polling (this was the
                        // bug that made the UI look permanently stuck).
                        if (discoveryUiState.phase !== 'pending') {
                            renderPendingFresh(tab, { status: 'pending', current_stage: 'Waiting for the audit job to be queued…' });
                        } else {
                            applyPendingUpdate({ status: 'pending', current_stage: 'Waiting for the audit job to be queued…' });
                        }
                        startDiscoveryPolling();
                    } else {
                        stopDiscoveryPolling();
                        stopDiscoveryTicker();
                        discoveryUiState.phase = 'error';
                        tab.innerHTML = '<div class="notice notice-error" style="padding:12px 16px;"><p>' + esc(msg) + '</p></div>';
                    }
                }
            })
            .catch(function () {
                // Network error — keep polling silently; the ticker keeps ticking
                // and "Last checked" will drift, making the stall visible.
            });
    }

    function showError(msg) {
        errorBox.innerHTML = '<div class="notice notice-error" style="padding:12px 16px;"><p style="font-size:14px;margin:0;">' + esc(msg) + '</p></div>';
    }

    /* ── Refresh button ───────────────────────────────── */

    var refreshBtn = document.getElementById('aeo-refresh-audit');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function (e) {
            e.preventDefault();
            loadAudit(true);
        });
    }

    /* ── Re-audit ────────────────────────────────────── */

    var reauditBtn      = document.getElementById('aeo-reaudit-btn');
    var reauditProgress = document.getElementById('aeo-reaudit-progress');
    var reauditStage    = document.getElementById('aeo-reaudit-stage');
    var reauditFill     = document.getElementById('aeo-reaudit-fill');
    var pollTimer       = null;

    var STAGE_PROGRESS = {
        'queued':      5,
        'pending':     10,
        'discovering': 30,
        'auditing':    55,
        'seeding':     75,
        'visibility':  90,
        'completed':   100,
        'failed':      100
    };

    var STAGE_LABELS = {
        'queued':      'Queued — waiting to start...',
        'pending':     'Pending — in the queue...',
        'discovering': 'Discovering — crawling your site...',
        'auditing':    'Auditing — analyzing content...',
        'seeding':     'Seeding — generating report...',
        'visibility':  'Visibility — testing AI engines...',
        'completed':   'Completed!',
        'failed':      'Audit failed.'
    };

    function showReauditProgress(stage) {
        reauditProgress.style.display = '';
        var pct = STAGE_PROGRESS[stage] || 5;
        var label = STAGE_LABELS[stage] || ('Processing: ' + stage + '...');
        reauditStage.textContent = label;
        reauditFill.style.width = pct + '%';

        if (stage === 'failed') {
            reauditFill.style.background = '#ea4335';
        } else if (stage === 'completed') {
            reauditFill.style.background = '#34a853';
        } else {
            reauditFill.style.background = '#4285f4';
        }
    }

    function hideReauditProgress() {
        reauditProgress.style.display = 'none';
        reauditFill.style.width = '0%';
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        reauditBtn.disabled = false;
        reauditBtn.textContent = 'Re-audit';
    }

    var lastPolledStatus = null;
    var STAGES_WITH_DISCOVERY = { auditing: 1, seeding: 1, visibility: 1, completed: 1 };

    function pollAuditStatus() {
        var data = new FormData();
        data.append('action', 'aeocas_audit_status');
        data.append('nonce', aeocasAudit.nonce);

        fetch(aeocasAudit.ajaxUrl, { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) {
                    stopPolling();
                    showReauditProgress('failed');
                    return;
                }

                var d = res.data.data || res.data;
                var status = d.status || d.current_stage || 'pending';

                showReauditProgress(status);

                // The moment the remote job crosses from "discovering" into a later
                // stage, the discovery JSONB column has been populated. Refresh the
                // Discovery tab once so users see findings as soon as they're ready,
                // even though the audit itself is still running.
                if (STAGES_WITH_DISCOVERY[status] && !STAGES_WITH_DISCOVERY[lastPolledStatus]) {
                    loadDiscovery(true);
                }
                lastPolledStatus = status;

                if (status === 'completed') {
                    stopPolling();
                    setTimeout(function () {
                        hideReauditProgress();
                        loadAudit(true);
                        loadDiscovery(true);
                    }, 1500);
                } else if (status === 'failed') {
                    stopPolling();
                    setTimeout(function () {
                        hideReauditProgress();
                        showError('Re-audit failed. Please try again later.');
                    }, 2000);
                }
            })
            .catch(function () {
                // Network error, keep polling.
            });
    }

    function triggerReaudit() {
        reauditBtn.disabled = true;
        reauditBtn.textContent = 'Running...';
        lastPolledStatus = null;
        errorBox.innerHTML = '';
        // Reset Discovery UI state so the elapsed counter starts at 0 and the
        // pending card re-paints cleanly for the new audit run.
        stopDiscoveryTicker();
        stopDiscoveryPolling();
        discoveryUiState.phase = 'idle';
        // Keep the tab container visible so users watch Discovery populate live.
        showReauditProgress('queued');
        // Kick Discovery into "pending" state immediately.
        loadDiscovery(true);

        var data = new FormData();
        data.append('action', 'aeocas_reaudit');
        data.append('nonce', aeocasAudit.nonce);

        fetch(aeocasAudit.ajaxUrl, { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) {
                    stopPolling();
                    hideReauditProgress();
                    showError(res.data && res.data.message ? res.data.message : 'Failed to trigger re-audit.');
                    return;
                }

                // Start polling every 10 seconds.
                pollTimer = setInterval(pollAuditStatus, 10000);
                // Also poll immediately after a short delay.
                setTimeout(pollAuditStatus, 3000);
            })
            .catch(function (err) {
                stopPolling();
                hideReauditProgress();
                showError('Network error: ' + (err.message || 'Please try again.'));
            });
    }

    if (reauditBtn) {
        reauditBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (pollTimer) return; // Already running.
            triggerReaudit();
        });
    }

    // Delegated handler for "Run Full Site Audit" buttons inside tab panels.
    wrap.addEventListener('click', function (e) {
        if (e.target.classList.contains('aeo-trigger-reaudit')) {
            e.preventDefault();
            if (pollTimer) return;
            // Switch to Discovery tab (position 0) so user sees the live progress.
            var tabs = wrap.querySelectorAll('.nav-tab');
            var panels = wrap.querySelectorAll('.aeo-tab-panel');
            tabs.forEach(function (t) { t.classList.remove('nav-tab-active'); });
            panels.forEach(function (p) { p.style.display = 'none'; });
            tabs[0].classList.add('nav-tab-active');
            panels[0].style.display = '';
            triggerReaudit();
        }
    });

    // Delegated handler for Site Audit filter dropdowns.
    wrap.addEventListener('change', function (e) {
        var t = e.target;
        if (t && t.getAttribute && t.getAttribute('data-aeo-filter')) {
            var key = t.getAttribute('data-aeo-filter');
            siteAuditFilters[key] = t.value;
            refreshSiteAuditTableOnly();
        }
    });

    // Delegated handler for Site Audit search input (live filter).
    wrap.addEventListener('input', function (e) {
        if (e.target && e.target.id === 'aeo-site-audit-search') {
            siteAuditFilters.search = e.target.value || '';
            refreshSiteAuditTableOnly();
        }
    });

    // Delegated handler for clicking the AEO score (opens breakdown modal).
    wrap.addEventListener('click', function (e) {
        var trigger = e.target.closest && e.target.closest('.aeo-score-trigger');
        if (trigger) {
            e.preventDefault();
            openScoreModal();
        }
        if (e.target && e.target.classList && e.target.classList.contains('aeo-modal-close')) {
            closeScoreModal();
        }
    });

    // Close modal on backdrop click.
    document.addEventListener('click', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('aeo-modal-backdrop')) {
            closeScoreModal();
        }
    });

    // Close modal on Escape.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeScoreModal();
    });

    /* ── Util ─────────────────────────────────────────── */

    function esc(str) {
        if (!str) return '';
        // Decode HTML entities first (API returns &#8211; etc.), then safely escape.
        var tmp = document.createElement('textarea');
        tmp.innerHTML = str;
        var decoded = tmp.value;
        var el = document.createElement('span');
        el.textContent = decoded;
        return el.innerHTML;
    }

    /* ── Init ─────────────────────────────────────────── */

    // Show the tab container immediately — Discovery and each audit tab render
    // their own loading/empty states so the shell is always visible.
    loading.style.display = 'none';
    content.style.display = '';

    initTabs();
    initAccordions();

    // Honor ?tab=<slug> URL param: switch to that tab on load if it exists.
    // Falls back to the data-requested-tab attribute written by the PHP
    // template, which captures the same param server-side.
    (function () {
        var requested = wrap.getAttribute('data-requested-tab') || '';
        if (!requested) {
            try {
                var params = new URLSearchParams(window.location.search);
                requested = params.get('tab') || '';
            } catch (e) { /* ignore */ }
        }
        if (!requested) return;
        var target = wrap.querySelector('.nav-tab[data-tab="' + requested + '"]');
        var panel  = document.getElementById('tab-' + requested);
        if (!target || !panel) return;
        wrap.querySelectorAll('.nav-tab').forEach(function (t) { t.classList.remove('nav-tab-active'); });
        wrap.querySelectorAll('.aeo-tab-panel').forEach(function (p) { p.style.display = 'none'; });
        target.classList.add('nav-tab-active');
        panel.style.display = '';
    })();

    loadDiscovery(false);
    loadAudit(false);

})();
