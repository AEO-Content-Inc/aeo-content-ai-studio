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

        // Right: score circle + legend
        html += '<div class="aeo-hero-right">';
        html += renderScoreCircle(audit.overall_score, 100, 100);
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

    /* ── Render Pages Tab ─────────────────────────────── */

    function renderPages(audit) {
        var pages = audit.pages_reviewed || [];
        if (!pages.length) {
            return '<div class="aeo-log-empty"><p>No page data available. Run a full site audit from the AEO Content dashboard to see individual page scores.</p></div>';
        }

        // Build link graph lookup for inbound link counts.
        var linkGraph = audit.link_graph || {};
        var nodes = linkGraph.nodes || [];
        var inLinksMap = {};
        nodes.forEach(function (n) { inLinksMap[n.url] = n.inDegree || 0; });

        // Stats
        var totalPages = pages.length;
        var scored = pages.filter(function (p) { return p.pageRankScore > 0 || (p.pageRank && p.pageRank.score > 0); });
        var scores = scored.map(function (p) { return p.pageRankScore || (p.pageRank ? p.pageRank.score : 0); });
        var avgScore = scores.length ? Math.round(scores.reduce(function (a, b) { return a + b; }, 0) / scores.length) : 0;
        var strongCount = scores.filter(function (s) { return s >= 70; }).length;
        var needsAttention = scores.filter(function (s) { return s < 70; }).length;

        var html = '';

        // Stats bar
        html += '<div class="aeo-log-stats" style="margin-bottom:16px;">';
        html += '<div class="aeo-stat-card"><span class="aeo-stat-number">' + totalPages + '</span><span class="aeo-stat-label">Total Pages</span></div>';
        html += '<div class="aeo-stat-card"><span class="aeo-stat-number">' + avgScore + '</span><span class="aeo-stat-label">Avg AEO Rank</span></div>';
        html += '<div class="aeo-stat-card"><span class="aeo-stat-number aeo-stat-success">' + strongCount + '</span><span class="aeo-stat-label">Strong (70+)</span></div>';
        html += '<div class="aeo-stat-card"><span class="aeo-stat-number" style="color:#ea4335;">' + needsAttention + '</span><span class="aeo-stat-label">Needs Attention</span></div>';
        html += '</div>';

        // Sort pages by score descending
        var sorted = pages.slice().sort(function (a, b) {
            var sa = a.pageRankScore || (a.pageRank ? a.pageRank.score : 0);
            var sb = b.pageRankScore || (b.pageRank ? b.pageRank.score : 0);
            return sb - sa;
        });

        // Table
        html += '<table class="widefat fixed striped" style="margin-top:0;">';
        html += '<thead><tr>';
        html += '<th>Page</th>';
        html += '<th style="width:90px;">Type</th>';
        html += '<th style="width:80px;">AEO Rank</th>';
        html += '<th style="width:70px;">Words</th>';
        html += '<th style="width:70px;">In Links</th>';
        html += '</tr></thead><tbody>';

        sorted.forEach(function (page) {
            var score = page.pageRankScore || (page.pageRank ? page.pageRank.score : 0);
            var color = scoreColor100(score);
            var bg = scoreBg100(score);
            var cat = page.category || '';
            var words = page.wordCount || 0;
            var inLinks = inLinksMap[page.url] || 0;
            var shortUrl = page.url.replace(/^https?:\/\/[^/]+/, '');

            html += '<tr>';
            html += '<td>';
            html += '<div style="font-weight:600;font-size:13px;">' + esc(page.title || shortUrl) + '</div>';
            html += '<div style="font-size:11px;color:#646970;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:500px;">' + esc(shortUrl) + '</div>';
            html += '</td>';
            html += '<td><span class="aeo-log-command">' + esc(cat) + '</span></td>';
            html += '<td><span class="aeo-score-badge-pill" style="background:' + bg + ';color:' + color + ';">' + score + '</span></td>';
            html += '<td style="color:#646970;">' + (words > 0 ? words.toLocaleString() : '-') + '</td>';
            html += '<td style="color:#646970;">' + inLinks + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        return html;
    }

    /* ── Render Rewrite Candidates Tab ────────────────── */

    function renderRewriteCandidates(audit) {
        var pages = audit.pages_reviewed || [];
        if (!pages.length) {
            return '<div class="aeo-log-empty"><p>No page data available. Run a full site audit to see rewrite candidates.</p></div>';
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
            html += '<div style="font-size:11px;color:#646970;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:400px;">' + esc(shortUrl) + '</div>';
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

    /* ── Render All ───────────────────────────────────── */

    function renderAudit(audit) {
        document.getElementById('tab-overview').innerHTML      = renderOverview(audit);
        document.getElementById('tab-scoreboard').innerHTML    = renderScoreboard(audit);
        document.getElementById('tab-opportunities').innerHTML = renderOpportunities(audit);
        document.getElementById('tab-pages').innerHTML         = renderPages(audit);
        document.getElementById('tab-rewrite').innerHTML       = renderRewriteCandidates(audit);

        loading.style.display = 'none';
        content.style.display = '';
    }

    /* ── Load Audit Data ──────────────────────────────── */

    function loadAudit(refresh) {
        loading.style.display = '';
        content.style.display = 'none';
        errorBox.innerHTML = '';

        var data = new FormData();
        data.append('action', 'aeocas_get_audit');
        data.append('nonce', aeocasAudit.nonce);
        if (refresh) data.append('refresh', '1');

        fetch(aeocasAudit.ajaxUrl, { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    renderAudit(res.data);
                } else {
                    var msg = 'Failed to load audit.';
                    if (res.data) {
                        if (typeof res.data === 'string') msg = res.data;
                        else if (res.data.message) msg = res.data.message;
                    }
                    showError(msg);
                }
            })
            .catch(function (err) {
                showError('Network error: ' + (err.message || 'Please try again.'));
            });
    }

    function showError(msg) {
        loading.style.display = 'none';
        content.style.display = 'none';
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

                if (status === 'completed') {
                    stopPolling();
                    setTimeout(function () {
                        hideReauditProgress();
                        loadAudit(true);
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
        loading.style.display = 'none';
        content.style.display = 'none';
        errorBox.innerHTML = '';
        showReauditProgress('queued');

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

    /* ── Util ─────────────────────────────────────────── */

    function esc(str) {
        var el = document.createElement('span');
        el.textContent = str;
        return el.innerHTML;
    }

    /* ── Init ─────────────────────────────────────────── */

    initTabs();
    initAccordions();
    loadAudit(false);

})();
