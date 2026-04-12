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
    var isConnected = wrap.getAttribute('data-connected') === '1';
    var STAGE_CONFIGS = [
        { id: 'connect',   order: 1, title: 'Connect',   label: 'Connect and review discovery', tabs: ['connect', 'discovery'], defaultTab: 'connect' },
        { id: 'diagnose',  order: 2, title: 'Diagnose',  label: 'Find critical issues',         tabs: ['scoreboard', 'site-audit'], defaultTab: 'scoreboard' },
        { id: 'fix',       order: 3, title: 'Fix',       label: 'Act on best opportunities',    tabs: ['opportunities', 'rewrite'], defaultTab: 'opportunities' },
        { id: 'visibility', order: 4, title: 'AI Visibility', label: 'Monitor mentions and trends', tabs: ['visibility-overview', 'visibility-citations', 'visibility-competitors', 'visibility-trends'], defaultTab: 'visibility-overview' }
    ];
    var STAGE_BY_ID = {};
    var TAB_TO_STAGE = {};
    var activeStageId = 'connect';
    var stageTabState = {
        connect: 'connect',
        diagnose: 'scoreboard',
        fix: 'opportunities',
        visibility: 'visibility-overview'
    };

    STAGE_CONFIGS.forEach(function (stage) {
        STAGE_BY_ID[stage.id] = stage;
        stage.tabs.forEach(function (tabId) {
            TAB_TO_STAGE[tabId] = stage.id;
        });
    });

    /* ── Workflow Rail ────────────────────────────────── */

    function getStageShell(stageId) {
        return document.getElementById('stage-' + stageId);
    }

    function getPrimaryStep(stageId) {
        return wrap.querySelector('.aeo-workflow-step[data-stage="' + stageId + '"]');
    }

    function updateLinkTabParam(link, tabId) {
        if (!link || !tabId) return;
        var href = link.getAttribute('href');
        if (!href) return;
        try {
            var url = new URL(href, window.location.href);
            url.searchParams.set('tab', tabId);
            link.setAttribute('href', url.toString());
        } catch (e) {
            // Ignore malformed URLs and keep the existing href.
        }
    }

    function updateUrlTab(tabId) {
        if (!tabId || !window.history || !window.history.replaceState) return;
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', tabId);
            window.history.replaceState({}, '', url.toString());
        } catch (e) {
            // Ignore URL update failures.
        }
    }

    function getCurrentTabForStage(stageId) {
        var stage = STAGE_BY_ID[stageId];
        if (!stage) return '';
        return stageTabState[stageId] || stage.defaultTab;
    }

    function syncStagePanels(stageId) {
        STAGE_CONFIGS.forEach(function (stage) {
            var shell = getStageShell(stage.id);
            if (shell) {
                var isActiveStage = stage.id === stageId;
                shell.style.display = isActiveStage ? '' : 'none';
                shell.classList.toggle('is-active', isActiveStage);
            }

            var step = getPrimaryStep(stage.id);
            if (step) {
                step.classList.toggle('is-active', stage.id === stageId);
                step.setAttribute('aria-current', stage.id === stageId ? 'step' : 'false');
            }

            var activeTab = getCurrentTabForStage(stage.id);
            if (step) {
                updateLinkTabParam(step, activeTab);
            }
            stage.tabs.forEach(function (tabId) {
                var panel = document.getElementById('tab-' + tabId);
                if (!panel) return;
                var shouldShow = stage.id === stageId && (stage.tabs.length === 1 || tabId === activeTab);
                panel.style.display = shouldShow ? '' : 'none';
            });

            var subtabWrap = wrap.querySelector('.aeo-subtabs[data-subtabs-for="' + stage.id + '"]');
            if (subtabWrap) {
                subtabWrap.querySelectorAll('.aeo-subtab').forEach(function (btn) {
                    var isActiveSubtab = btn.getAttribute('data-tab') === activeTab;
                    btn.classList.toggle('is-active', isActiveSubtab);
                    btn.setAttribute('aria-selected', isActiveSubtab ? 'true' : 'false');
                    btn.setAttribute('aria-current', isActiveSubtab ? 'page' : 'false');
                });
            }
        });
    }

    function activateStage(stageId, preferredTab, skipUrl) {
        var stage = STAGE_BY_ID[stageId];
        if (!stage) return;
        var targetTab = preferredTab && stage.tabs.indexOf(preferredTab) !== -1 ? preferredTab : getCurrentTabForStage(stageId);
        if (!targetTab) targetTab = stage.defaultTab;

        stageTabState[stageId] = targetTab;
        activeStageId = stageId;
        syncStagePanels(stageId);
        if (!skipUrl) updateUrlTab(targetTab);
    }

    function activateTab(tabId, skipUrl) {
        var stageId = TAB_TO_STAGE[tabId];
        if (!stageId) return;
        activateStage(stageId, tabId, skipUrl);
    }

    function normalizeRequestedView(requested) {
        if (!requested) return 'connect';
        if (requested === 'activity' || requested === 'visibility') return 'visibility-overview';
        if (TAB_TO_STAGE[requested]) return requested;
        if (STAGE_BY_ID[requested]) return STAGE_BY_ID[requested].defaultTab;
        return 'connect';
    }

    function initWorkflowRail() {
        wrap.querySelectorAll('.aeo-workflow-step').forEach(function (step) {
            step.addEventListener('click', function (e) {
                e.preventDefault();
                var stageId = this.getAttribute('data-stage');
                activateStage(stageId);
            });
        });
    }

    function initStageSubtabs() {
        wrap.querySelectorAll('.aeo-subtab').forEach(function (subtab) {
            subtab.addEventListener('click', function (e) {
                e.preventDefault();
                var tabId = this.getAttribute('data-tab');
                activateTab(tabId);
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
    var FAQ_BASE_URL       = 'https://www.aeocontent.ai/faq';
    var FAQ_TOPIC_LIBRARY  = {
        'audit': {
            label: 'The AEO Audit',
            url: FAQ_BASE_URL + '/audit',
            items: [
                { question: 'What are the 48 AEO criteria?', keywords: ['criteria', 'score', 'audit', 'pillar'] },
                { question: 'How do I read the audit scorecard?', keywords: ['scorecard', 'status', 'finding', 'audit'] }
            ]
        },
        'technical': {
            label: 'Technical Implementation',
            url: FAQ_BASE_URL + '/technical',
            items: [
                { question: 'What is llms.txt and do I need one?', keywords: ['llms', 'discovery', 'file'] },
                { question: 'What Schema.org markup helps with AI visibility?', keywords: ['schema', 'structured', 'json', 'markup'] },
                { question: 'How do I structure content for AI extraction?', keywords: ['extract', 'structure', 'content', 'html'] },
                { question: 'Does my robots.txt affect AI visibility?', keywords: ['robots', 'crawler', 'bot', 'allow'] }
            ]
        },
        'content-strategy': {
            label: 'Content Strategy for AI',
            url: FAQ_BASE_URL + '/content-strategy',
            items: [
                { question: 'How does direct answer density work?', keywords: ['answer', 'direct', 'paragraph', 'query'] },
                { question: 'What role does author schema play in AI visibility?', keywords: ['author', 'expert', 'entity', 'trust'] }
            ]
        },
        'technical-audit': {
            label: 'Technical Audit Criteria',
            url: FAQ_BASE_URL + '/technical-audit',
            items: [
                { question: 'How is table and list extractability scored?', keywords: ['table', 'list', 'extractability', 'html'] },
                { question: 'What content licensing signals does the audit check?', keywords: ['licensing', 'license', 'ai.txt', 'tdm'] }
            ]
        },
        'ai-visibility': {
            label: 'AI Visibility & Citations',
            url: FAQ_BASE_URL + '/ai-visibility',
            items: [
                { question: 'What is the difference between AEO Site Rank and AEO Page Rank?', keywords: ['page', 'rank', 'site', 'score'] },
                { question: 'How does Reddit presence affect AI citation rates?', keywords: ['reddit', 'citation', 'visibility', 'brand'] }
            ]
        }
    };
    var FAQ_TOPIC_BY_SLUG = {
        'llms-txt':                ['technical', 'audit'],
        'robots-txt-ai':           ['technical', 'technical-audit'],
        'structured-data':         ['technical', 'technical-audit'],
        'schema-coverage-ratio':   ['technical', 'technical-audit'],
        'clean-html':              ['technical', 'technical-audit'],
        'semantic-html':           ['technical', 'technical-audit'],
        'table-list-extractability':['technical', 'technical-audit'],
        'qa-content':              ['content-strategy', 'audit'],
        'faq-section':             ['content-strategy', 'technical'],
        'direct-answer-density':   ['content-strategy', 'audit'],
        'query-answer-alignment':  ['content-strategy', 'audit'],
        'topic-coherence':         ['content-strategy', 'audit'],
        'content-depth':           ['content-strategy', 'audit'],
        'content-cannibalization': ['content-strategy', 'audit'],
        'content-freshness':       ['technical-audit', 'ai-visibility'],
        'visible-date-signal':     ['technical-audit', 'ai-visibility'],
        'rss-feed-presence':       ['technical-audit', 'technical'],
        'content-velocity':        ['ai-visibility', 'audit'],
        'entity-authority':        ['ai-visibility', 'content-strategy'],
        'author-expert-schema':    ['content-strategy', 'ai-visibility'],
        'fact-density':            ['content-strategy', 'ai-visibility'],
        'content-licensing':       ['technical-audit', 'technical'],
        'canonical-url-strategy':  ['technical-audit', 'technical'],
        'internal-linking':        ['content-strategy', 'technical-audit'],
        'definition-patterns':     ['content-strategy', 'audit']
    };
    var FAQ_TOPICS_BY_CATEGORY = {
        'answer':       ['content-strategy', 'audit'],
        'structure':    ['technical', 'content-strategy'],
        'trust':        ['ai-visibility', 'technical-audit'],
        'technical':    ['technical', 'technical-audit'],
        'discovery':    ['technical', 'ai-visibility'],
        'content':      ['content-strategy', 'audit'],
        'substance':    ['content-strategy', 'audit'],
        'organization': ['technical', 'content-strategy'],
        'plumbing':     ['technical', 'technical-audit']
    };
    var OPPORTUNITY_STOP_WORDS = {
        'the':1, 'and':1, 'for':1, 'with':1, 'that':1, 'this':1, 'from':1, 'your':1,
        'into':1, 'have':1, 'will':1, 'when':1, 'what':1, 'where':1, 'need':1, 'site':1,
        'page':1, 'pages':1, 'content':1, 'more':1, 'about':1, 'than':1, 'them':1, 'been':1,
        'they':1, 'their':1, 'into':1, 'across':1, 'should':1, 'could':1, 'would':1, 'while':1,
        'also':1, 'there':1, 'here':1, 'very':1, 'much':1, 'only':1, 'over':1, 'under':1
    };

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

    function buildDetailedFindingsMap(audit) {
        var map = {};
        (audit.detailed_findings || []).forEach(function (c) {
            map[c.id] = c.findings || [];
        });
        return map;
    }

    function trimText(text, maxLen) {
        if (!text) return '';
        var str = String(text).replace(/\s+/g, ' ').trim();
        if (!maxLen || str.length <= maxLen) return str;
        return str.slice(0, Math.max(0, maxLen - 1)).replace(/\s+\S*$/, '') + '…';
    }

    function normalizeToken(token) {
        var t = String(token || '').toLowerCase().replace(/[^a-z0-9]/g, '');
        if (!t) return '';
        if (t.length > 5 && t.slice(-3) === 'ies') t = t.slice(0, -3) + 'y';
        else if (t.length > 4 && t.slice(-2) === 'es') t = t.slice(0, -2);
        else if (t.length > 4 && t.slice(-1) === 's') t = t.slice(0, -1);
        return t;
    }

    function tokenizeText(value) {
        if (!value) return [];
        var seen = {};
        return String(value)
            .toLowerCase()
            .replace(/https?:\/\/\S+/g, ' ')
            .replace(/[^a-z0-9]+/g, ' ')
            .split(/\s+/)
            .map(normalizeToken)
            .filter(function (token) {
                if (!token || token.length < 3 || OPPORTUNITY_STOP_WORDS[token]) return false;
                if (seen[token]) return false;
                seen[token] = true;
                return true;
            });
    }

    function tokenOverlapCount(aTokens, bTokens) {
        if (!aTokens || !bTokens || !aTokens.length || !bTokens.length) return 0;
        var bLookup = {};
        bTokens.forEach(function (token) { bLookup[token] = 1; });
        var count = 0;
        aTokens.forEach(function (token) {
            if (bLookup[token]) count++;
        });
        return count;
    }

    function uniqueBy(items, getKey) {
        var seen = {};
        var out = [];
        (items || []).forEach(function (item) {
            if (!item) return;
            var key = getKey(item);
            if (!key || seen[key]) return;
            seen[key] = true;
            out.push(item);
        });
        return out;
    }

    function impactRank(value) {
        var impact = String(value || '').toLowerCase();
        if (impact === 'critical') return 4;
        if (impact === 'high') return 3;
        if (impact === 'medium') return 2;
        if (impact === 'low') return 1;
        return 0;
    }

    function isCriticalImpact(value) {
        var impact = String(value || '').toLowerCase();
        return impact === 'critical' || impact === 'high';
    }

    function isCriticalScorecardItem(item) {
        var status = String(item && item.status || '').toLowerCase();
        if (statusLabel(status) === 'critical') return true;
        if (status === 'critical' || status === 'high' || status === 'fix immediately') return true;
        return typeof (item && item.score) === 'number' && item.score > 0 && item.score < 4;
    }

    function getImpactColor(value) {
        var impact = String(value || '').toLowerCase();
        if (impact === 'critical' || impact === 'high') return '#ea4335';
        if (impact === 'medium') return '#f9ab00';
        if (impact === 'low') return '#34a853';
        return '#646970';
    }

    function getEffortColor(value) {
        var effort = String(value || '').toLowerCase();
        if (effort === 'low') return '#34a853';
        if (effort === 'medium') return '#f9ab00';
        if (effort === 'high') return '#ea4335';
        return '#646970';
    }

    function getNormalizedCategoryKey(category) {
        if (!category || !category.key) return 'other';
        if (category.key === 'answer' || category.key === 'content' || category.key === 'substance') return 'answer';
        if (category.key === 'structure' || category.key === 'organization') return 'structure';
        if (category.key === 'trust') return 'trust';
        if (category.key === 'technical' || category.key === 'plumbing') return 'technical';
        if (category.key === 'discovery') return 'discovery';
        return category.key;
    }

    function getWeakestPagePillar(page) {
        if (!page || !page.pillarScores) return null;
        var pillars = [
            { key: 'answer',    label: 'Answer Readiness',    score: page.pillarScores.answerReadiness || 0 },
            { key: 'structure', label: 'Content Structure',   score: page.pillarScores.contentStructure || 0 },
            { key: 'trust',     label: 'Trust & Authority',   score: page.pillarScores.trustAuthority || 0 },
            { key: 'technical', label: 'Technical Foundation',score: page.pillarScores.technicalFoundation || 0 },
            { key: 'discovery', label: 'AI Discovery',        score: page.pillarScores.aiDiscovery || 0 }
        ].filter(function (pillar) {
            return typeof pillar.score === 'number';
        });
        if (!pillars.length) return null;
        pillars.sort(function (a, b) { return a.score - b.score; });
        return pillars[0];
    }

    function pageHasCriticalIssue(page) {
        if (!page) return false;
        var pagePriority = String(page.priority || page.status || '').toLowerCase();
        if (pagePriority === 'critical' || pagePriority === 'high') return true;
        if (getPageScore(page) > 0 && getPageScore(page) < 40) return true;
        return (page.issues || []).some(function (issue) {
            var sev = String(issue && issue.severity || '').toLowerCase();
            return sev === 'critical' || sev === 'high';
        });
    }

    function buildOpportunityText(opp) {
        return [
            opp && opp.name,
            opp && opp.description,
            opp && opp.status,
            opp && opp.impact,
            opp && opp.type,
            opp && opp.category
        ].filter(Boolean).join(' ');
    }

    function findMatchedCriteriaForOpportunity(opp, scorecard, cats, findingsMap) {
        var explicitIds = [];
        var explicitId = firstNonEmpty(opp.criterion_id, opp.criterionId);
        if (explicitId) explicitIds.push(parseInt(explicitId, 10));
        if (Array.isArray(opp.criteria_ids)) {
            opp.criteria_ids.forEach(function (id) {
                if (!id) return;
                explicitIds.push(parseInt(id, 10));
            });
        }

        if (explicitIds.length) {
            return uniqueBy(explicitIds.map(function (id) {
                var item = scorecard.filter(function (row) { return row.id === id; })[0];
                if (!item) return null;
                return {
                    item: item,
                    slug: getCriterionSlug(item.id, scorecard),
                    category: getCategoryForId(item.id, cats),
                    findings: findingsMap[item.id] || [],
                    matchScore: 100
                };
            }), function (match) {
                return match && match.item ? String(match.item.id) : '';
            }).filter(Boolean);
        }

        var oppText = buildOpportunityText(opp);
        var oppTextLower = oppText.toLowerCase();
        var oppTokens = tokenizeText(oppText);

        var matches = scorecard.map(function (item) {
            var slug = getCriterionSlug(item.id, scorecard) || '';
            var findings = findingsMap[item.id] || [];
            var findingText = findings.map(function (finding) {
                return [finding.type, finding.severity, finding.description].filter(Boolean).join(' ');
            }).join(' ');
            var criterionText = [item.criterion, item.keyFindings, slug.replace(/-/g, ' '), findingText].join(' ');
            var critTokens = tokenizeText(criterionText);
            var score = tokenOverlapCount(oppTokens, critTokens) * 6;

            if (item.criterion && oppTextLower.indexOf(String(item.criterion).toLowerCase()) !== -1) score += 18;
            if (slug) {
                var slugText = slug.replace(/-/g, ' ');
                if (oppTextLower.indexOf(slugText) !== -1) score += 16;
                score += tokenOverlapCount(oppTokens, tokenizeText(slugText)) * 4;
            }
            if (isCriticalScorecardItem(item)) score += 1;
            if (typeof item.score === 'number' && item.score < 5) score += 1;

            return {
                item: item,
                slug: slug,
                category: getCategoryForId(item.id, cats),
                findings: findings,
                matchScore: score
            };
        }).filter(function (match) {
            return match.matchScore > 0;
        });

        matches.sort(function (a, b) {
            if (b.matchScore !== a.matchScore) return b.matchScore - a.matchScore;
            return (a.item.score || 0) - (b.item.score || 0);
        });

        if (!matches.length) {
            return scorecard.slice().sort(function (a, b) {
                return (a.score || 0) - (b.score || 0);
            }).slice(0, 2).map(function (item) {
                return {
                    item: item,
                    slug: getCriterionSlug(item.id, scorecard),
                    category: getCategoryForId(item.id, cats),
                    findings: findingsMap[item.id] || [],
                    matchScore: 0
                };
            });
        }

        return uniqueBy(matches, function (match) {
            return String(match.item.id);
        }).slice(0, 3);
    }

    function buildKnowledgeLinksForOpportunity(matches, primaryCategory) {
        var links = matches.map(function (match) {
            if (!match || !match.slug) return null;
            return {
                label: match.item.criterion || match.slug.replace(/-/g, ' '),
                url: KNOWLEDGE_BASE_URL + match.slug,
                topic: match.category ? match.category.label : (primaryCategory ? primaryCategory.label : 'Knowledge Base'),
                meta: typeof match.item.score === 'number' ? ('Current score: ' + match.item.score + '/10') : ''
            };
        }).filter(Boolean);

        if (!links.length) {
            links.push({
                label: 'AEO Score Methodology',
                url: KNOWLEDGE_BASE_URL + 'aeo-score-methodology',
                topic: primaryCategory ? primaryCategory.label : 'Knowledge Base',
                meta: 'Scoring and prioritization guide'
            });
        }

        return uniqueBy(links, function (link) {
            return link.url;
        }).slice(0, 3);
    }

    function faqQuestionScore(contextTokens, item) {
        return tokenOverlapCount(contextTokens, tokenizeText(item.question + ' ' + (item.keywords || []).join(' ')));
    }

    function buildFaqLinksForOpportunity(opp, matches, primaryCategory) {
        var topicKeys = [];
        matches.forEach(function (match) {
            (FAQ_TOPIC_BY_SLUG[match.slug] || []).forEach(function (topicKey) {
                topicKeys.push(topicKey);
            });
        });
        if (!topicKeys.length && primaryCategory) {
            topicKeys = FAQ_TOPICS_BY_CATEGORY[primaryCategory.key] || FAQ_TOPICS_BY_CATEGORY[getNormalizedCategoryKey(primaryCategory)] || [];
        }
        if (!topicKeys.length) {
            topicKeys = ['audit', 'technical'];
        }

        var contextTokens = tokenizeText(
            buildOpportunityText(opp) + ' ' +
            matches.map(function (match) {
                return [match.item.criterion, match.slug].join(' ');
            }).join(' ')
        );

        var links = [];
        uniqueBy(topicKeys.map(function (topicKey) { return { topicKey: topicKey }; }), function (entry) {
            return entry.topicKey;
        }).forEach(function (entry) {
            var topic = FAQ_TOPIC_LIBRARY[entry.topicKey];
            if (!topic) return;
            var rankedItems = topic.items.slice().sort(function (a, b) {
                return faqQuestionScore(contextTokens, b) - faqQuestionScore(contextTokens, a);
            });
            rankedItems.slice(0, 2).forEach(function (item) {
                links.push({
                    label: item.question,
                    url: topic.url,
                    topic: topic.label,
                    meta: 'Open FAQ topic'
                });
            });
        });

        return uniqueBy(links, function (link) {
            return link.url + '|' + link.label;
        }).slice(0, 4);
    }

    function buildRelatedPagesForOpportunity(opp, audit, matches, primaryCategory, inLinksMap) {
        var pages = (audit && audit.pages_reviewed) || [];
        if (!pages.length) return [];

        var contextTokens = tokenizeText(
            buildOpportunityText(opp) + ' ' +
            matches.map(function (match) {
                return [
                    match.item.criterion,
                    match.slug,
                    (match.findings || []).map(function (finding) {
                        return [finding.type, finding.severity, finding.description].filter(Boolean).join(' ');
                    }).join(' ')
                ].join(' ');
            }).join(' ')
        );
        var targetPillar = getNormalizedCategoryKey(primaryCategory);
        var faqIntent = contextTokens.indexOf('faq') !== -1 || contextTokens.indexOf('question') !== -1;

        var ranked = pages.map(function (page) {
            var score = 0;
            var reasons = [];
            var pageScore = getPageScore(page);
            var inboundLinks = lookupInLinks(inLinksMap, page.url);
            var weakest = getWeakestPagePillar(page);
            var local = getLocalContentForUrl(page.url);
            var issuesText = (page.issues || []).map(function (issue) {
                return [issue.label, issue.check, issue.severity].filter(Boolean).join(' ');
            }).join(' ');
            var pageText = [page.title, page.url, page.category, issuesText].concat(page.topFixes || []).join(' ');
            var overlap = tokenOverlapCount(contextTokens, tokenizeText(pageText));

            if (pageScore > 0) {
                score += Math.max(0, 70 - pageScore);
                if (pageScore < 40) reasons.push('low AEO rank');
                else if (pageScore < 55) reasons.push('needs attention');
            }
            if (inboundLinks > 0) {
                score += Math.min(inboundLinks * 4, 18);
                if (inboundLinks >= 3) reasons.push('high leverage');
            }
            if (pageHasCriticalIssue(page)) {
                score += 14;
                reasons.push('critical issue');
            }
            if (weakest && weakest.key === targetPillar) {
                score += 12;
                reasons.push(weakest.label.toLowerCase() + ' gap');
            }
            if (overlap > 0) {
                score += overlap * 7;
                reasons.push('matching issue set');
            }
            if (local && faqIntent) {
                if (local.has_faq) {
                    reasons.push(local.faq_count > 1 ? (local.faq_count + ' FAQ items') : 'FAQ ready');
                } else {
                    score += 8;
                    reasons.push('no FAQ yet');
                }
            }

            return {
                url: page.url,
                title: page.title || page.url,
                shortUrl: (page.url || '').replace(/^https?:\/\/[^/]+/, '') || page.url || '',
                score: pageScore,
                issueCount: getPageIssueCount(page),
                inLinks: inboundLinks,
                editUrl: local && local.edit_url ? local.edit_url : '',
                faqCount: local && typeof local.faq_count === 'number' ? local.faq_count : 0,
                hasFaq: !!(local && local.has_faq),
                reasons: uniqueBy(reasons.map(function (reason) { return { reason: reason }; }), function (entry) {
                    return entry.reason;
                }).map(function (entry) { return entry.reason; }).slice(0, 3),
                priorityScore: score
            };
        });

        ranked.sort(function (a, b) {
            if (b.priorityScore !== a.priorityScore) return b.priorityScore - a.priorityScore;
            if (a.score !== b.score) return a.score - b.score;
            return b.inLinks - a.inLinks;
        });

        return uniqueBy(ranked, function (page) {
            return page.url;
        }).slice(0, 4);
    }

    function buildOpportunityActions(opp, matches, relatedPages) {
        var actions = [];
        if (opp && opp.description) {
            actions.push(trimText(opp.description, 165));
        }

        matches.forEach(function (match) {
            (match.findings || []).forEach(function (finding) {
                if (!finding || !finding.description) return;
                var severity = String(finding.severity || '').toUpperCase();
                if (severity === 'GOOD' || severity === 'WORKING' || severity === 'GOOD PATTERN') return;
                actions.push(trimText(finding.description, 165));
            });
        });

        if (relatedPages.length) {
            var pageTitles = relatedPages.slice(0, 2).map(function (page) {
                return '"' + trimText(page.title, 48) + '"';
            });
            actions.push('Start on ' + pageTitles.join(' and ') + ' because they combine low scores with the strongest leverage.');
        }

        return uniqueBy(actions.map(function (text) {
            return { text: text };
        }), function (entry) {
            return String(entry.text || '').toLowerCase();
        }).map(function (entry) {
            return entry.text;
        }).filter(Boolean).slice(0, 3);
    }

    function buildOpportunityWhyNow(primaryCategory, relatedPages, potentialGain) {
        var parts = [];
        if (primaryCategory) {
            parts.push(primaryCategory.label + ' is where this gap is suppressing visibility.');
        }
        if (relatedPages.length) {
            var leverageCount = relatedPages.filter(function (page) { return page.inLinks >= 3; }).length;
            if (leverageCount > 0) {
                parts.push(leverageCount + ' linked page' + (leverageCount === 1 ? '' : 's') + ' can move quickly once fixed.');
            } else {
                parts.push('The pages below are the fastest places to apply the fix.');
            }
        }
        if (potentialGain > 0) {
            parts.push('Potential recovery: +' + potentialGain + ' points on the weakest linked signal.');
        }
        return trimText(parts.join(' '), 220);
    }

    function buildOpportunityModels(audit) {
        var opps = audit.opportunities || [];
        var scorecard = audit.scorecard || [];
        var cats = getCategories(scorecard);
        var findingsMap = buildDetailedFindingsMap(audit);
        var inLinksMap = buildInLinksMap(audit);

        return opps.map(function (opp) {
            var matchedCriteria = findMatchedCriteriaForOpportunity(opp, scorecard, cats, findingsMap);
            var primaryCategory = matchedCriteria[0] ? matchedCriteria[0].category : null;
            var impact = opp.impact || 'N/A';
            var effort = opp.effort || '';
            var potentialGain = 0;
            matchedCriteria.forEach(function (match) {
                potentialGain = Math.max(potentialGain, Math.max(0, 10 - (match.item.score || 0)));
            });
            if (!potentialGain && impactRank(impact) > 0) {
                potentialGain = impactRank(impact) * 2;
            }

            var relatedPages = buildRelatedPagesForOpportunity(opp, audit, matchedCriteria, primaryCategory, inLinksMap);
            var knowledgeLinks = buildKnowledgeLinksForOpportunity(matchedCriteria, primaryCategory);
            var faqLinks = buildFaqLinksForOpportunity(opp, matchedCriteria, primaryCategory);
            var actions = buildOpportunityActions(opp, matchedCriteria, relatedPages);
            var isCritical = isCriticalImpact(impact) || matchedCriteria.some(function (match) {
                return isCriticalScorecardItem(match.item);
            });
            var priorityScore = impactRank(impact) * 100 + potentialGain * 8 + (String(effort || '').toLowerCase() === 'low' ? 12 : 0);

            return {
                opp: opp,
                impact: impact,
                effort: effort,
                impactColor: getImpactColor(impact),
                effortColor: getEffortColor(effort),
                matchedCriteria: matchedCriteria,
                primaryCategory: primaryCategory,
                potentialGain: potentialGain,
                relatedPages: relatedPages,
                knowledgeLinks: knowledgeLinks,
                faqLinks: faqLinks,
                actions: actions,
                isCritical: isCritical,
                whyNow: buildOpportunityWhyNow(primaryCategory, relatedPages, potentialGain),
                priorityScore: priorityScore
            };
        }).sort(function (a, b) {
            return b.priorityScore - a.priorityScore;
        });
    }

    /* ── Render Scoreboard Tab ────────────────────────── */

    function renderScoreboard(audit) {
        var scorecard = audit.scorecard || [];
        if (!scorecard.length) return '<p>No scorecard data available.</p>';
        var cats = getCategories(scorecard);

        // Build detailed findings lookup by criterion id
        var findingsMap = buildDetailedFindingsMap(audit);

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
        var models = buildOpportunityModels(audit);
        if (!models.length) return '<p>No opportunities identified.</p>';

        var uniquePages = {};
        var resourceCount = 0;
        var quickWins = 0;
        var criticalCount = 0;

        models.forEach(function (model) {
            if (model.isCritical) criticalCount++;
            if (String(model.effort || '').toLowerCase() === 'low') quickWins++;
            model.relatedPages.forEach(function (page) { uniquePages[page.url] = 1; });
            resourceCount += model.knowledgeLinks.length + model.faqLinks.length;
        });

        var html = '';
        html += '<div class="aeo-opportunities-shell">';
        html +=   '<div class="aeo-site-audit-header">';
        html +=     '<h2>Opportunities</h2>';
        html +=     '<p class="description">Priority fixes with the best pages to edit first, the right AEO guides, and related FAQs for implementation context.</p>';
        html +=     '<p><a href="#" class="button button-primary aeo-trigger-reaudit">Run Full Site Re-Audit</a></p>';
        html +=   '</div>';
        html +=   '<div class="aeo-log-stats">';
        html +=     '<div class="aeo-stat-card"><span class="aeo-stat-number">' + models.length + '</span><span class="aeo-stat-label">Total Opportunities</span></div>';
        html +=     '<div class="aeo-stat-card"><span class="aeo-stat-number" style="color:#ea4335;">' + criticalCount + '</span><span class="aeo-stat-label">Critical Now</span></div>';
        html +=     '<div class="aeo-stat-card"><span class="aeo-stat-number aeo-stat-success">' + quickWins + '</span><span class="aeo-stat-label">Quick Wins</span></div>';
        html +=     '<div class="aeo-stat-card"><span class="aeo-stat-number">' + Object.keys(uniquePages).length + '</span><span class="aeo-stat-label">High-Leverage Pages</span></div>';
        html +=     '<div class="aeo-stat-card"><span class="aeo-stat-number">' + resourceCount + '</span><span class="aeo-stat-label">Guides & FAQs</span></div>';
        html +=   '</div>';
        html +=   '<div class="aeo-opportunities-list">';

        models.forEach(function (model) {
            var opp = model.opp;

            html += '<div class="aeo-accordion-item aeo-opp-card">';
            html +=   '<div class="aeo-accordion-header aeo-opp-header">';
            html +=     '<span class="aeo-accordion-arrow">&#8963;</span>';
            html +=     '<span class="aeo-opp-impact" style="background:' + model.impactColor + '20;color:' + model.impactColor + ';">' + esc(model.impact || 'N/A') + '</span>';
            html +=     '<div class="aeo-opp-primary">';
            html +=       '<span class="aeo-opp-name">' + esc(opp.name || '') + '</span>';
            html +=       '<span class="aeo-opp-subtitle">' + esc(model.whyNow || 'Review the linked pages and guides below.') + '</span>';
            html +=     '</div>';
            html +=     '<div class="aeo-opp-meta">';
            if (model.primaryCategory) {
                html += '<span class="aeo-cat-badge" style="background:' + model.primaryCategory.bg + ';color:' + model.primaryCategory.color + ';">' + esc(model.primaryCategory.label) + '</span>';
            }
            if (model.potentialGain > 0) {
                html += '<span class="aeo-opp-potential">+ ' + model.potentialGain + ' pts</span>';
            }
            if (model.effort) {
                html += '<span class="aeo-opp-effort" style="background:' + model.effortColor + '20;color:' + model.effortColor + ';border:1px solid ' + model.effortColor + '30;">' + esc(model.effort) + '</span>';
            }
            html +=     '</div>';
            html +=   '</div>';

            html +=   '<div class="aeo-accordion-body" style="display:none;">';
            html +=     '<div class="aeo-opp-body">';
            html +=       '<div class="aeo-opp-summary"><p>' + esc(opp.description || 'No description provided.') + '</p></div>';
            html +=       '<div class="aeo-opp-grid">';

            html +=         '<section class="aeo-opp-panel">';
            html +=           '<h3 class="aeo-opp-panel-title">Related Site Pages</h3>';
            if (model.relatedPages.length) {
                html += '<div class="aeo-opp-page-list">';
                model.relatedPages.forEach(function (page) {
                    var pageColor = scoreColor100(page.score || 0);
                    var pageBg = scoreBg100(page.score || 0);
                    html += '<div class="aeo-opp-page-item">';
                    html +=   '<div class="aeo-opp-page-main">';
                    html +=     '<div class="aeo-opp-page-title">' + esc(page.title) + '</div>';
                    html +=     '<div class="aeo-opp-page-url">' + esc(page.shortUrl) + '</div>';
                    html +=     '<div class="aeo-opp-page-actions">';
                    html +=       '<a href="' + esc(page.url) + '" target="_blank" rel="noopener">View live &#8599;</a>';
                    if (page.editUrl) {
                        html += '<a href="' + esc(page.editUrl) + '">Edit</a>';
                    }
                    html +=     '</div>';
                    if (page.reasons.length) {
                        html += '<div class="aeo-opp-page-reasons">';
                        page.reasons.forEach(function (reason) {
                            html += '<span class="aeo-opp-reason-chip">' + esc(reason) + '</span>';
                        });
                        html += '</div>';
                    }
                    html +=   '</div>';
                    html +=   '<div class="aeo-opp-page-side">';
                    html +=     '<span class="aeo-score-badge-pill" style="background:' + pageBg + ';color:' + pageColor + ';">' + (page.score > 0 ? page.score : '—') + '</span>';
                    html +=     '<span class="aeo-opp-page-meta">' + (page.issueCount || 0) + ' issue' + ((page.issueCount || 0) === 1 ? '' : 's') + '</span>';
                    html +=   '</div>';
                    html += '</div>';
                });
                html += '</div>';
            } else {
                html += '<p class="aeo-opp-empty">No related pages surfaced in the latest audit yet.</p>';
            }
            html +=         '</section>';

            html +=         '<section class="aeo-opp-panel">';
            html +=           '<h3 class="aeo-opp-panel-title">Knowledge Base Topics</h3>';
            html +=           '<div class="aeo-opp-link-list">';
            model.knowledgeLinks.forEach(function (link) {
                html += '<a href="' + esc(link.url) + '" target="_blank" rel="noopener" class="aeo-opp-link-item">';
                html +=   '<span class="aeo-opp-link-kicker">' + esc(link.topic) + '</span>';
                html +=   '<span class="aeo-opp-link-title">' + esc(link.label) + '</span>';
                if (link.meta) html += '<span class="aeo-opp-link-meta">' + esc(link.meta) + '</span>';
                html += '</a>';
            });
            html +=           '</div>';
            html +=         '</section>';

            html +=         '<section class="aeo-opp-panel">';
            html +=           '<h3 class="aeo-opp-panel-title">Related FAQs</h3>';
            html +=           '<div class="aeo-opp-link-list">';
            model.faqLinks.forEach(function (link) {
                html += '<a href="' + esc(link.url) + '" target="_blank" rel="noopener" class="aeo-opp-link-item">';
                html +=   '<span class="aeo-opp-link-kicker">' + esc(link.topic) + '</span>';
                html +=   '<span class="aeo-opp-link-title">' + esc(link.label) + '</span>';
                if (link.meta) html += '<span class="aeo-opp-link-meta">' + esc(link.meta) + '</span>';
                html += '</a>';
            });
            html +=           '</div>';
            html +=         '</section>';

            html +=         '<section class="aeo-opp-panel">';
            html +=           '<h3 class="aeo-opp-panel-title">What To Do Now</h3>';
            if (model.actions.length) {
                html += '<ul class="aeo-opp-action-list">';
                model.actions.forEach(function (action) {
                    html += '<li>' + esc(action) + '</li>';
                });
                html += '</ul>';
            } else {
                html += '<p class="aeo-opp-empty">Use the related pages, guides, and FAQs above to scope the fix.</p>';
            }
            html +=         '</section>';

            html +=       '</div>';
            html +=     '</div>';
            html +=   '</div>';
            html += '</div>';
        });

        html +=   '</div>';
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

    function normalizeUrlKey(url) {
        if (!url) return '';
        return String(url).replace(/\/+$/, '').replace(/^https?:\/\//, '').replace(/^www\./, '').toLowerCase();
    }

    function buildInLinksMap(audit) {
        var m = {};
        var g = (audit && audit.link_graph) || (audit && audit.linkGraph) || {};
        var nodes = g.nodes || g.Nodes || [];
        var edges = g.edges || g.Edges || [];

        // First pass: read inDegree/in_degree directly from nodes if present.
        var anyInDegree = false;
        nodes.forEach(function (n) {
            if (!n) return;
            var url = n.url || n.URL || n.href || '';
            var deg = n.inDegree;
            if (deg == null) deg = n.in_degree;
            if (deg == null) deg = n.inbound;
            if (deg == null) deg = n.inboundCount;
            if (typeof deg === 'number') {
                m[normalizeUrlKey(url)] = deg;
                if (deg > 0) anyInDegree = true;
            }
        });

        // Second pass: if nothing looked like a degree field, compute from
        // edges by counting incoming hits per target URL. Handles snake_case
        // and different shapes.
        if (!anyInDegree && edges.length) {
            edges.forEach(function (e) {
                if (!e) return;
                var target = e.target || e.to || e.dst || e.destination || '';
                if (!target) return;
                var key = normalizeUrlKey(target);
                m[key] = (m[key] || 0) + 1;
            });
        }

        return m;
    }

    function lookupInLinks(map, url) {
        return map[normalizeUrlKey(url)] || 0;
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
                case 'links-desc':  return lookupInLinks(inLinks, b.url) - lookupInLinks(inLinks, a.url);
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
            var il    = lookupInLinks(inLinks, page.url);
            var shortUrl = (page.url || '').replace(/^https?:\/\/[^/]+/, '');
            var issueCount = getPageIssueCount(page);
            var issueHtml = issueCount > 0
                ? '<span class="aeo-site-audit-issues">' + issueCount + ' issue' + (issueCount === 1 ? '' : 's') + '</span>'
                : '';

            var scoreCell = score > 0
                ? '<button type="button" class="aeo-score-badge-pill aeo-page-score-trigger" data-page-url="' + esc(page.url) + '" style="background:' + bg + ';color:' + color + ';" title="Click for breakdown">' + score + '</button>'
                : '<span class="aeo-score-badge-pill" style="background:#f0f0f1;color:#646970;">—</span>';

            return ''
                + '<tr>'
                +   '<td>'
                +     '<div class="aeo-site-audit-title">' + esc(page.title || shortUrl) + '</div>'
                +     '<div class="aeo-site-audit-url"><a href="' + esc(page.url) + '" target="_blank" rel="noopener">' + esc(shortUrl || page.url) + ' &#8599;</a></div>'
                +     (issueHtml ? '<div class="aeo-site-audit-issue-row">' + issueHtml + '</div>' : '')
                +   '</td>'
                +   '<td><span class="aeo-log-command">' + esc(cat) + '</span></td>'
                +   '<td>' + scoreCell + '</td>'
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
            +   '<h2>Pages Audit</h2>'
            +   '<p class="description">Every page the platform crawled, scored against the full AEO criteria set.</p>'
            +   '<p><a href="#" class="button button-primary aeo-trigger-reaudit">Run Full Site Re-Audit</a></p>'
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
        return renderAuditWaiting(
            'Pages audit is running…',
            'We\'re crawling every page on your site and scoring it against the full AEO criteria set. This page will update automatically as soon as results are in.'
        );
    }

    /* ── Render Rewrite Candidates Tab ────────────────── */

    function buildRewriteCandidates(audit) {
        var pages = audit.pages_reviewed || [];
        if (!pages.length) return [];

        var inLinksMap = buildInLinksMap(audit);
        var candidates = pages.filter(function (page) {
            var score = getPageScore(page);
            return score > 0 && score < 70;
        }).map(function (page) {
            var score = getPageScore(page);
            var tier = score < 40 ? 'high' : score < 55 ? 'medium' : 'low';
            var inLinks = lookupInLinks(inLinksMap, page.url);
            var weakest = getWeakestPagePillar(page);
            var priority = (100 - score) + Math.min(inLinks * 5, 50);

            return {
                url: page.url,
                title: page.title || page.url,
                category: page.category || '',
                score: score,
                tier: tier,
                inLinks: inLinks,
                words: page.wordCount || 0,
                weakest: weakest ? { name: weakest.label, score: weakest.score } : null,
                priority: priority,
                topFixes: page.topFixes || [],
                isStale: page.issues ? page.issues.some(function (issue) { return (issue.check || '').indexOf('freshness') !== -1; }) : false
            };
        });

        candidates.sort(function (a, b) { return b.priority - a.priority; });
        return candidates;
    }

    function renderRewriteCandidates(audit) {
        var candidates = buildRewriteCandidates(audit);
        if (!audit.pages_reviewed || !audit.pages_reviewed.length) {
            return renderAuditWaiting(
                'Rewrite candidates loading…',
                'Pages scoring below 70 will appear here as rewrite candidates once the audit completes.'
            );
        }

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
    // empty state when no audit is loaded). Connect has server-rendered
    // content, while visibility is fetched separately.
    var AUDIT_TAB_IDS = ['site-audit', 'scoreboard', 'opportunities', 'rewrite'];
    var VISIBILITY_TAB_IDS = ['visibility-overview', 'visibility-citations', 'visibility-competitors', 'visibility-trends'];
    var discoveryPollTimer = null;
    var auditRetryTimer = null;
    var currentAuditData = null;
    var currentDiscoveryPayload = null;
    var currentVisibilityPayload = null;
    var visibilityUiState = {
        phase: 'idle',
        message: ''
    };
    var localContentByUrlKey = {};
    var localContentIndexPromise = null;
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

    function ingestLocalContentIndex(items) {
        localContentByUrlKey = {};
        (items || []).forEach(function (item) {
            if (!item) return;
            [item.url, item.canonical_url].forEach(function (url) {
                var key = normalizeUrlKey(url);
                if (!key || localContentByUrlKey[key]) return;
                localContentByUrlKey[key] = item;
            });
        });
    }

    function getLocalContentForUrl(url) {
        return localContentByUrlKey[normalizeUrlKey(url)] || null;
    }

    function loadLocalContentIndex() {
        if (localContentIndexPromise) return localContentIndexPromise;

        var data = new FormData();
        data.append('action', 'aeocas_get_local_content_index');
        data.append('nonce', aeocasAudit.nonce);

        localContentIndexPromise = fetch(aeocasAudit.ajaxUrl, { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success) return;
                ingestLocalContentIndex((res.data && res.data.items) || []);
                if (currentAuditData) renderAudit(currentAuditData);
            })
            .catch(function () {
                // Keep the audit UI usable even if the local index fails.
            });

        return localContentIndexPromise;
    }

    function ensureCountBadge(host, className) {
        if (!host) return null;
        var badge = host.querySelector('.' + className);
        if (!badge) {
            badge = document.createElement('span');
            badge.className = className;
            host.appendChild(badge);
        }
        return badge;
    }

    function setCountBadge(host, className, count) {
        var badge = ensureCountBadge(host, className);
        if (!badge) return;
        if (!count) {
            badge.textContent = '';
            badge.classList.remove('is-visible');
            return;
        }
        badge.textContent = String(count);
        badge.classList.add('is-visible');
    }

    function clearCriticalBadges() {
        wrap.querySelectorAll('.aeo-workflow-badge, .aeo-subtab-badge').forEach(function (badge) {
            badge.textContent = '';
            badge.classList.remove('is-visible');
        });
    }

    function buildStageCounts(context) {
        var discoveryFailed = (context.discovery && context.discovery.status === 'failed') || discoveryUiState.phase === 'error';
        var visibility = context.visibility || buildVisibilitySnapshot();
        return {
            stages: {
                connect: (context.connected ? 0 : 1) + (discoveryFailed ? 1 : 0),
                diagnose: context.scorecardCritical + context.pageCritical,
                fix: context.opportunityCritical + context.rewriteCritical,
                visibility: visibility.criticalCount
            },
            subtabs: {
                connect: context.connected ? 0 : 1,
                discovery: discoveryFailed ? 1 : 0,
                scoreboard: context.scorecardCritical,
                'site-audit': context.pageCritical,
                opportunities: context.opportunityCritical,
                rewrite: context.rewriteCritical,
                'visibility-overview': visibility.criticalCount,
                'visibility-citations': visibility.citationIssueCount,
                'visibility-competitors': visibility.competitorThreatCount,
                'visibility-trends': visibility.trendIssueCount + visibility.staleCount
            }
        };
    }

    function updateWorkflowBadges(context) {
        if (!context) {
            clearCriticalBadges();
            return;
        }

        var counts = buildStageCounts(context);
        Object.keys(counts.stages).forEach(function (stageId) {
            setCountBadge(getPrimaryStep(stageId), 'aeo-workflow-badge', counts.stages[stageId]);
        });
        Object.keys(counts.subtabs).forEach(function (tabId) {
            var subtab = wrap.querySelector('.aeo-subtab[data-tab="' + tabId + '"]');
            setCountBadge(subtab, 'aeo-subtab-badge', counts.subtabs[tabId]);
        });
    }

    function renderAuditWaiting(title, description) {
        // Shared waiting card used by all audit-driven tabs when no data
        // is available yet. Shows the same rotating verb + stage + progress
        // bar as the Discovery pending card, driven by shared discoveryUiState.
        var st   = discoveryUiState;
        var status = st.status || 'pending';
        var stage  = st.currentStage || STAGE_LABELS[status] || 'Waiting for the audit worker...';
        var pct    = STAGE_PROGRESS[status] || 5;
        var verb   = DISCOVERY_VERBS[(st.verbIdx || 0) % DISCOVERY_VERBS.length];

        return ''
            + '<div class="aeo-discovery-pending">'
            +   '<h2>' + esc(title || 'Audit is running…') + '</h2>'
            +   '<p class="description">' + esc(description || 'The platform is analyzing your site. Results will appear here automatically once the audit completes.') + '</p>'
            +   '<p class="aeo-disc-verb-row"><span class="aeo-disc-verb-static">' + esc(verb) + '…</span></p>'
            +   '<div class="aeo-reaudit-track"><div class="aeo-reaudit-fill aeo-disc-fill" style="width:' + pct + '%;"></div></div>'
            +   '<p class="aeo-disc-stage-row">'
            +     '<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span>'
            +     '<span>' + esc(stage) + '</span>'
            +   '</p>'
            +   '<p style="text-align:center;margin-top:14px;">'
            +     '<a href="#" class="button aeo-trigger-reaudit">Run Full Site Audit</a>'
            +   '</p>'
            + '</div>';
    }

    function renderAuditEmpty(message) {
        return renderAuditWaiting(
            'Waiting for audit data…',
            message || 'No audit data yet. Click below to start a full site audit.'
        );
    }

    function setAuditTabsLoading() {
        clearCriticalBadges();
        AUDIT_TAB_IDS.forEach(function (id) {
            var el = document.getElementById('tab-' + id);
            if (el) el.innerHTML = renderAuditLoading();
        });
        refreshWorkflowChrome();
    }

    function setAuditTabsEmpty(message) {
        clearCriticalBadges();
        AUDIT_TAB_IDS.forEach(function (id) {
            var el = document.getElementById('tab-' + id);
            if (el) el.innerHTML = renderAuditEmpty(message);
        });
        refreshWorkflowChrome();
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

    function toNumber(value) {
        if (typeof value === 'number') {
            return isNaN(value) ? null : value;
        }
        if (typeof value === 'string') {
            var cleaned = value.replace(/[^0-9.+-]/g, '');
            if (!cleaned) return null;
            var parsed = parseFloat(cleaned);
            return isNaN(parsed) ? null : parsed;
        }
        return null;
    }

    function firstNumber() {
        for (var i = 0; i < arguments.length; i++) {
            var num = toNumber(arguments[i]);
            if (num !== null) return num;
        }
        return null;
    }

    function hasNumber(value) {
        return toNumber(value) !== null;
    }

    function clampNumber(value, min, max) {
        if (!hasNumber(value)) return min;
        return Math.min(max, Math.max(min, toNumber(value)));
    }

    function formatCompactNumber(value) {
        if (!hasNumber(value)) return '—';
        var num = Math.round(toNumber(value));
        try {
            return num.toLocaleString();
        } catch (e) {
            return String(num);
        }
    }

    function formatSignedDelta(value) {
        if (!hasNumber(value)) return '—';
        var num = toNumber(value);
        var rounded = Math.round(num * 10) / 10;
        var sign = rounded > 0 ? '+' : '';
        return sign + rounded;
    }

    function normalizeVisibilitySeverity(value) {
        var severity = String(value || '').toLowerCase();
        if (['critical', 'high', 'error', 'failed', 'danger'].indexOf(severity) !== -1) return 'critical';
        if (['warning', 'warn', 'medium', 'moderate', 'attention'].indexOf(severity) !== -1) return 'warning';
        if (['healthy', 'success', 'good', 'ok', 'resolved'].indexOf(severity) !== -1) return 'healthy';
        return 'neutral';
    }

    function extractVisibilityPayload(payload) {
        if (!payload || typeof payload !== 'object') return null;
        if (payload.visibility && typeof payload.visibility === 'object') return payload.visibility;
        if (payload.data && payload.data.visibility && typeof payload.data.visibility === 'object') return payload.data.visibility;
        return payload;
    }

    function getVisibilityAdminUrl(snapshot) {
        if (snapshot && snapshot.adminUrl) return snapshot.adminUrl;
        var stage = getStageShell('visibility');
        if (stage) {
            var stageUrl = stage.getAttribute('data-admin-url');
            if (stageUrl) return stageUrl;
        }
        return (aeocasAudit && aeocasAudit.adminPluginUrl) ? aeocasAudit.adminPluginUrl : '';
    }

    function buildVisibilitySnapshot(payload) {
        if (payload && typeof payload === 'object' && payload.available !== undefined && Array.isArray(payload.alerts) && Array.isArray(payload.trendPoints)) {
            return payload;
        }

        var raw = extractVisibilityPayload(payload);
        if (!raw || typeof raw !== 'object') {
            return {
                available: false,
                status: '',
                adminUrl: getVisibilityAdminUrl(),
                score: null,
                delta7: null,
                delta30: null,
                citationsCount: 0,
                engineCount: 0,
                criticalAlerts: 0,
                warningAlerts: 0,
                criticalCount: 0,
                citationIssueCount: 0,
                competitorThreatCount: 0,
                trendIssueCount: 0,
                alerts: [],
                citations: [],
                topPages: [],
                engines: [],
                competitors: [],
                trendPoints: [],
                lastSyncedAt: '',
                isStale: false,
                staleCount: 0
            };
        }

        var status = String(firstNonEmpty(raw.status, raw.state, raw.sync_status, '') || '').toLowerCase();
        var score = firstNumber(raw.visibility_score, raw.ai_visibility_score, raw.score, raw.overall_score);
        var delta7 = firstNumber(raw.delta_7d, raw.score_delta_7d, raw.visibility_delta_7d, raw.weekly_delta);
        var delta30 = firstNumber(raw.delta_30d, raw.score_delta_30d, raw.visibility_delta_30d, raw.monthly_delta);
        var lastSyncedAt = firstNonEmpty(raw.last_synced_at, raw.last_refreshed_at, raw.updated_at, raw.synced_at, raw.generated_at, '');
        var alertsRaw = firstNonEmpty(raw.alerts, raw.visibility_alerts, raw.issues, raw.notifications, []);
        var citationsRaw = firstNonEmpty(raw.top_citations, raw.citations, raw.mentions, raw.recent_citations, raw.top_mentions, []);
        var competitorsRaw = firstNonEmpty(raw.competitors, raw.competitor_scores, raw.competitor_snapshot, []);
        var enginesRaw = firstNonEmpty(raw.engines, raw.engine_breakdown, raw.engine_counts, raw.sources, []);
        var trendRaw = firstNonEmpty(raw.trend_points_30d, raw.trend_points, raw.points, raw.history, raw.timeline, []);
        var adminUrl = firstNonEmpty(raw.admin_url, getVisibilityAdminUrl());
        var alerts = [];
        var citations = [];
        var competitors = [];
        var engines = [];
        var trendPoints = [];
        var topPagesMap = {};
        var engineMap = {};

        (Array.isArray(alertsRaw) ? alertsRaw : []).forEach(function (item, index) {
            if (!item) return;
            if (typeof item === 'string') {
                alerts.push({
                    id: 'alert-' + index,
                    severity: 'warning',
                    title: item,
                    detail: '',
                    category: '',
                    engine: ''
                });
                return;
            }

            alerts.push({
                id: firstNonEmpty(item.id, item.slug, 'alert-' + index),
                severity: normalizeVisibilitySeverity(firstNonEmpty(item.severity, item.level, item.tone, item.status)),
                title: firstNonEmpty(item.title, item.heading, item.label, item.name, 'Visibility alert'),
                detail: firstNonEmpty(item.detail, item.description, item.message, ''),
                category: String(firstNonEmpty(item.category, item.type, item.area, item.scope, '') || '').toLowerCase(),
                engine: firstNonEmpty(item.engine, item.source, item.platform, '')
            });
        });

        (Array.isArray(citationsRaw) ? citationsRaw : []).forEach(function (item, index) {
            if (!item) return;
            if (typeof item === 'string') {
                item = { query: item };
            }

            var engine = firstNonEmpty(item.engine, item.source, item.platform, 'AI Engine');
            var pageUrl = firstNonEmpty(item.page_url, item.url, item.target_url, item.cited_url, '');
            var pageTitle = firstNonEmpty(item.page_title, item.title, item.page, item.page_name, pageUrl || 'Cited page');
            var severity = normalizeVisibilitySeverity(firstNonEmpty(item.severity, item.level, item.status));
            var normalized = {
                id: firstNonEmpty(item.id, 'citation-' + index),
                engine: engine,
                query: firstNonEmpty(item.query, item.topic, item.prompt, item.keyword, ''),
                pageUrl: pageUrl,
                pageTitle: pageTitle,
                citedAt: firstNonEmpty(item.cited_at, item.detected_at, item.timestamp, item.date, ''),
                snippet: firstNonEmpty(item.snippet, item.quote, item.excerpt, ''),
                severity: severity
            };

            citations.push(normalized);
            if (engine) {
                engineMap[engine] = (engineMap[engine] || 0) + 1;
            }
            var pageKey = pageUrl || pageTitle;
            if (pageKey) {
                if (!topPagesMap[pageKey]) {
                    topPagesMap[pageKey] = {
                        title: pageTitle,
                        url: pageUrl,
                        count: 0
                    };
                }
                topPagesMap[pageKey].count += 1;
            }
        });

        if (Array.isArray(enginesRaw)) {
            enginesRaw.forEach(function (item, index) {
                if (item == null) return;
                if (typeof item === 'string') {
                    engines.push({ id: 'engine-' + index, name: item, count: engineMap[item] || 0 });
                    return;
                }

                var name = firstNonEmpty(item.name, item.engine, item.source, item.platform);
                if (!name) return;
                engines.push({
                    id: firstNonEmpty(item.id, 'engine-' + index),
                    name: name,
                    count: firstNumber(item.count, item.citations, item.mentions, item.total, engineMap[name]) || 0,
                    visibility_pct: firstNumber(item.visibility_pct, item.visibilityPct, item.score, item.percent),
                    tested_queries: firstNumber(item.tested_queries, item.testedQueries, item.total_queries)
                });
            });
        }

        if (!engines.length) {
            Object.keys(engineMap).forEach(function (name, index) {
                engines.push({ id: 'engine-derived-' + index, name: name, count: engineMap[name] });
            });
        }

        (Array.isArray(competitorsRaw) ? competitorsRaw : []).forEach(function (item, index) {
            if (!item) return;
            if (typeof item === 'string') {
                competitors.push({
                    id: 'competitor-' + index,
                    name: item,
                    visibilityScore: null,
                    delta30: null,
                    citationShare: null
                });
                return;
            }

            competitors.push({
                id: firstNonEmpty(item.id, item.slug, 'competitor-' + index),
                name: firstNonEmpty(item.name, item.domain, item.site, item.competitor, 'Competitor ' + (index + 1)),
                visibilityScore: firstNumber(item.visibility_score, item.score, item.ai_visibility_score),
                delta30: firstNumber(item.delta_30d, item.score_delta_30d, item.delta, item.change),
                citationShare: firstNumber(item.citation_share, item.share, item.share_pct, item.percentage),
                mention_count: firstNumber(item.mention_count, item.mentions, item.count)
            });
        });

        if (Array.isArray(trendRaw) && trendRaw.length) {
            trendRaw.forEach(function (item, index) {
                if (item == null) return;
                if (typeof item === 'number' || typeof item === 'string') {
                    var primitiveScore = toNumber(item);
                    if (primitiveScore === null) return;
                    trendPoints.push({ id: 'trend-' + index, label: String(index + 1), date: '', score: primitiveScore });
                    return;
                }

                var pointScore = firstNumber(item.score, item.visibility_score, item.value);
                if (pointScore === null) return;
                trendPoints.push({
                    id: firstNonEmpty(item.id, 'trend-' + index),
                    label: firstNonEmpty(item.label, item.date, item.day, String(index + 1)),
                    date: firstNonEmpty(item.date, item.day, item.label, ''),
                    score: pointScore
                });
            });
        }

        if (!trendPoints.length && score !== null) {
            var baseline30 = delta30 !== null ? score - delta30 : score;
            var baseline7 = delta7 !== null ? score - delta7 : score;
            trendPoints = [
                { id: 'trend-30d', label: '30d', date: '30d ago', score: baseline30 },
                { id: 'trend-7d', label: '7d', date: '7d ago', score: baseline7 },
                { id: 'trend-now', label: 'Now', date: 'Now', score: score }
            ];
        }

        var topPages = Object.keys(topPagesMap).map(function (key) { return topPagesMap[key]; })
            .sort(function (a, b) { return b.count - a.count; })
            .slice(0, 5);

        engines.sort(function (a, b) { return (b.count || 0) - (a.count || 0); });
        competitors.sort(function (a, b) {
            var aScore = a.visibilityScore == null ? -Infinity : a.visibilityScore;
            var bScore = b.visibilityScore == null ? -Infinity : b.visibilityScore;
            return bScore - aScore;
        });

        var citationsCount = firstNumber(raw.citations_count, raw.citation_count, raw.mentions_count, raw.total_citations, citations.length) || 0;
        var criticalAlerts = alerts.filter(function (alert) { return alert.severity === 'critical'; }).length;
        var warningAlerts = alerts.filter(function (alert) { return alert.severity === 'warning'; }).length;
        var citationIssueCount = citations.filter(function (citation) { return citation.severity === 'critical'; }).length
            + alerts.filter(function (alert) { return (alert.category || '').indexOf('citation') !== -1; }).length;
        var competitorThreatCount = competitors.filter(function (competitor) {
            return hasNumber(score) && hasNumber(competitor.visibilityScore) && competitor.visibilityScore >= score + 10;
        }).length;
        var trendIssueCount = 0;
        if (delta7 !== null && delta7 <= -5) trendIssueCount += 1;
        if (delta30 !== null && delta30 <= -10) trendIssueCount += 1;
        var isStale = false;
        if (lastSyncedAt) {
            var syncedDate = new Date(lastSyncedAt);
            if (!isNaN(syncedDate.getTime())) {
                isStale = (Date.now() - syncedDate.getTime()) > (24 * 60 * 60 * 1000);
            }
        }
        var staleCount = isStale ? 1 : 0;
        var criticalCount = criticalAlerts + trendIssueCount + staleCount;
        var hasStructuredData = !!(
            score !== null ||
            citationsCount > 0 ||
            alerts.length ||
            competitors.length ||
            engines.length ||
            trendPoints.length
        );
        var available = hasStructuredData || ['ready', 'completed', 'active', 'synced', 'healthy'].indexOf(status) !== -1;

        return {
            available: available,
            raw: raw,
            status: status,
            adminUrl: adminUrl,
            score: score,
            delta7: delta7,
            delta30: delta30,
            citationsCount: citationsCount,
            engineCount: engines.length,
            criticalAlerts: criticalAlerts,
            warningAlerts: warningAlerts,
            criticalCount: criticalCount,
            citationIssueCount: citationIssueCount,
            competitorThreatCount: competitorThreatCount,
            trendIssueCount: trendIssueCount,
            alerts: alerts,
            citations: citations,
            topPages: topPages,
            engines: engines,
            competitors: competitors,
            trendPoints: trendPoints,
            lastSyncedAt: lastSyncedAt,
            isStale: isStale,
            staleCount: staleCount
        };
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

        var currentVerb = DISCOVERY_VERBS[st.verbIdx % DISCOVERY_VERBS.length] + '…';
        if (verbEl) {
            verbEl.textContent = currentVerb;
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

        // Also update the verb + progress bar on ALL audit waiting cards
        // (Pages Audit, Rewrite, Scoreboard, Opportunities) which use static
        // verb elements since they share the same discoveryUiState.
        var pct = STAGE_PROGRESS[st.status] || 5;
        wrap.querySelectorAll('.aeo-disc-verb-static').forEach(function (el) {
            el.textContent = currentVerb;
        });
        wrap.querySelectorAll('.aeo-disc-fill').forEach(function (el) {
            el.style.width = pct + '%';
        });
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

        var synthesizedHint = d._synthesized
            ? '<div class="aeo-discovery-synthesized-hint">These findings were reconstructed from your latest audit. Click <strong>Re-run Discovery</strong> above to extract a richer profile with full topic analysis, voice signals, and competitor data.</div>'
            : '';

        return ''
            + '<div class="aeo-discovery-header">'
            +   '<div class="aeo-discovery-header-row">'
            +     '<h2>Discovery findings</h2>'
            +     '<a href="#" class="button button-primary aeo-rerun-discovery">&#8635; Re-run Discovery</a>'
            +   '</div>'
            +   '<p class="description">Everything the platform extracted about your site during the deterministic Discovery stage.</p>'
            +   meta
            +   synthesizedHint
            + '</div>'
            + '<div class="aeo-discovery-grid">' + cards + '</div>';
    }

    /* ── Visibility Tab ──────────────────────────────── */

    function renderVisibilityLoading(message) {
        return '<div class="aeo-tab-loading"><span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>' + esc(message || 'Loading AI visibility...') + '</div>';
    }

    function renderVisibilityAction(url, label, isPrimary) {
        if (!url) return '';
        return '<a href="' + esc(url) + '" class="button ' + (isPrimary ? 'button-primary' : 'button-secondary') + '" target="_blank" rel="noopener">' + esc(label) + '</a>';
    }

    function renderVisibilityCard(title, description, body, classes) {
        return ''
            + '<section class="aeo-visibility-card ' + esc(classes || '') + '">'
            +   '<div class="aeo-visibility-card-head">'
            +     '<div>'
            +       '<h3 class="aeo-visibility-card-title">' + esc(title || '') + '</h3>'
            +       (description ? '<p class="aeo-visibility-card-description">' + esc(description) + '</p>' : '')
            +     '</div>'
            +   '</div>'
            +   body
            + '</section>';
    }

    function renderVisibilityEmptyState(title, body, snapshot) {
        var adminUrl = getVisibilityAdminUrl(snapshot);
        return ''
            + '<div class="aeo-visibility-empty-state">'
            +   '<div class="aeo-visibility-empty-icon"><span class="dashicons dashicons-visibility"></span></div>'
            +   '<h3>' + esc(title || 'Visibility data is not ready yet') + '</h3>'
            +   '<p>' + esc(body || 'The plugin is waiting for a visibility snapshot from AEO Content Studio.') + '</p>'
            +   '<div class="aeo-visibility-empty-actions">'
            +     renderVisibilityAction(adminUrl, 'Open Full Admin', true)
            +   '</div>'
            + '</div>';
    }

    function renderVisibilityAdminCard(snapshot, compact) {
        var adminUrl = getVisibilityAdminUrl(snapshot);
        var classes = 'aeo-visibility-admin-card' + (compact ? ' aeo-visibility-card-compact' : '');
        var body = ''
            + '<div class="aeo-visibility-admin-body">'
            +   '<p class="aeo-visibility-admin-copy">The WordPress plugin now shows visibility insights only. Operational logs, command history, and deeper troubleshooting live in AEO admin.</p>'
            +   '<div class="aeo-visibility-admin-actions">'
            +     renderVisibilityAction(adminUrl, 'Open Full Admin', true)
            +   '</div>'
            + '</div>';
        return renderVisibilityCard('Admin workspace', 'Logs moved out of the plugin', body, classes);
    }

    function renderVisibilityAlertList(snapshot) {
        var alerts = snapshot && snapshot.alerts ? snapshot.alerts.slice(0, 5) : [];
        var items = '';

        if (snapshot && snapshot.isStale) {
            items += ''
                + '<li class="aeo-visibility-alert-item is-warning">'
                +   '<span class="aeo-visibility-alert-pill">Stale</span>'
                +   '<div class="aeo-visibility-alert-copy">'
                +     '<strong>Visibility snapshot is stale</strong>'
                +     '<span>Last synced ' + esc(snapshot.lastSyncedAt ? formatDate(snapshot.lastSyncedAt) : 'more than 24 hours ago') + '.</span>'
                +   '</div>'
                + '</li>';
        }

        alerts.forEach(function (alert) {
            items += ''
                + '<li class="aeo-visibility-alert-item is-' + esc(alert.severity || 'neutral') + '">'
                +   '<span class="aeo-visibility-alert-pill">' + esc((alert.severity || 'notice').toUpperCase()) + '</span>'
                +   '<div class="aeo-visibility-alert-copy">'
                +     '<strong>' + esc(alert.title || 'Visibility alert') + '</strong>'
                +     (alert.detail ? '<span>' + esc(alert.detail) + '</span>' : '')
                +   '</div>'
                + '</li>';
        });

        if (!items) {
            return '<div class="aeo-visibility-empty"><strong>No urgent visibility alerts.</strong><span>The latest snapshot looks stable.</span></div>';
        }

        return '<ul class="aeo-visibility-alerts">' + items + '</ul>';
    }

    function renderVisibilityEngineRows(snapshot) {
        if (!snapshot || !snapshot.engines || !snapshot.engines.length) {
            return '<div class="aeo-visibility-empty"><strong>Engine coverage will appear here.</strong><span>The first completed visibility sync will populate engine-level citations.</span></div>';
        }

        var max = snapshot.engines.reduce(function (largest, engine) {
            var value = hasNumber(engine.visibility_pct) ? toNumber(engine.visibility_pct) : (engine.count || 0);
            return Math.max(largest, value || 0);
        }, 1);

        return '<div class="aeo-visibility-engine-list">' + snapshot.engines.slice(0, 6).map(function (engine) {
            var value = hasNumber(engine.visibility_pct) ? toNumber(engine.visibility_pct) : (engine.count || 0);
            var pct = hasNumber(engine.visibility_pct)
                ? clampNumber(engine.visibility_pct, 0, 100)
                : clampNumber((value / max) * 100, 0, 100);
            var label = hasNumber(engine.visibility_pct)
                ? Math.round(toNumber(engine.visibility_pct)) + '%'
                : formatCompactNumber(engine.count || 0);
            return ''
                + '<div class="aeo-visibility-engine-row">'
                +   '<div class="aeo-visibility-engine-head">'
                +     '<strong>' + esc(engine.name || 'Engine') + '</strong>'
                +     '<span>' + esc(label) + '</span>'
                +   '</div>'
                +   '<div class="aeo-visibility-engine-bar"><span style="width:' + pct + '%;"></span></div>'
                + '</div>';
        }).join('') + '</div>';
    }

    function renderVisibilitySparkline(snapshot) {
        var points = (snapshot && snapshot.trendPoints) || [];
        if (!points.length) {
            return '<div class="aeo-visibility-empty"><strong>Trend data will appear after the first sync.</strong><span>Once visibility history is available, this chart will show the score trajectory.</span></div>';
        }

        var width = 320;
        var height = 120;
        var pad = 12;
        var scores = points.map(function (point) { return point.score; });
        var min = Math.min.apply(null, scores);
        var max = Math.max.apply(null, scores);
        if (min === max) {
            min -= 1;
            max += 1;
        }

        var coords = points.map(function (point, index) {
            var x = pad + ((width - (pad * 2)) * (points.length === 1 ? 0.5 : (index / (points.length - 1))));
            var y = height - pad - (((point.score - min) / (max - min)) * (height - (pad * 2)));
            return { x: x, y: y, point: point };
        });

        var polyline = coords.map(function (coord) {
            return coord.x.toFixed(1) + ',' + coord.y.toFixed(1);
        }).join(' ');
        var lastCoord = coords[coords.length - 1];
        var firstLabel = points[0] && (points[0].date || points[0].label) ? (points[0].date || points[0].label) : '';
        var lastLabel = lastCoord.point && (lastCoord.point.date || lastCoord.point.label) ? (lastCoord.point.date || lastCoord.point.label) : '';

        return ''
            + '<div class="aeo-visibility-trend-chart">'
            +   '<svg viewBox="0 0 ' + width + ' ' + height + '" preserveAspectRatio="none" role="img" aria-label="Visibility score trend">'
            +     '<path d="M ' + coords[0].x.toFixed(1) + ' ' + (height - pad) + ' L ' + polyline.replace(/ /g, ' L ') + ' L ' + lastCoord.x.toFixed(1) + ' ' + (height - pad) + ' Z" fill="rgba(15,118,110,0.12)"></path>'
            +     '<polyline fill="none" stroke="#0f766e" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" points="' + polyline + '"></polyline>'
            +     '<circle cx="' + lastCoord.x.toFixed(1) + '" cy="' + lastCoord.y.toFixed(1) + '" r="5" fill="#0f766e"></circle>'
            +   '</svg>'
            +   '<div class="aeo-visibility-trend-foot">'
            +     '<span>' + esc(String(firstLabel || 'Start')) + '</span>'
            +     '<span>' + esc(String(lastLabel || 'Now')) + '</span>'
            +   '</div>'
            + '</div>';
    }

    function renderVisibilityTopPages(snapshot) {
        if (!snapshot || !snapshot.topPages || !snapshot.topPages.length) {
            return '<div class="aeo-visibility-empty"><strong>No cited pages yet.</strong><span>Top cited URLs will appear after the first visibility capture.</span></div>';
        }

        return '<div class="aeo-visibility-list">' + snapshot.topPages.map(function (page) {
            var titleHtml = page.url
                ? '<a href="' + esc(page.url) + '" target="_blank" rel="noopener">' + esc(page.title || page.url) + '</a>'
                : '<span>' + esc(page.title || 'Page') + '</span>';
            return ''
                + '<div class="aeo-visibility-list-row">'
                +   '<div class="aeo-visibility-list-copy">'
                +     '<strong>' + titleHtml + '</strong>'
                +     (page.url ? '<span>' + esc(page.url) + '</span>' : '')
                +   '</div>'
                +   '<span class="aeo-visibility-list-count">' + esc(formatCompactNumber(page.count || 0)) + '</span>'
                + '</div>';
        }).join('') + '</div>';
    }

    function renderVisibilityCitationRows(snapshot) {
        if (!snapshot || !snapshot.citations || !snapshot.citations.length) {
            return '<div class="aeo-visibility-empty"><strong>No citations recorded yet.</strong><span>Recent mentions from AI engines will populate here once the visibility sync completes.</span></div>';
        }

        return '<div class="aeo-visibility-list">' + snapshot.citations.slice(0, 12).map(function (citation) {
            var tone = citation.severity === 'critical' ? ' aeo-visibility-list-row-critical' : '';
            var pageHtml = citation.pageUrl
                ? '<a href="' + esc(citation.pageUrl) + '" target="_blank" rel="noopener">' + esc(citation.pageTitle || citation.pageUrl) + '</a>'
                : '<span>' + esc(citation.pageTitle || 'Cited page') + '</span>';
            var meta = [citation.engine, citation.query, citation.citedAt ? formatDate(citation.citedAt) : ''].filter(Boolean).join(' · ');

            return ''
                + '<div class="aeo-visibility-list-row' + tone + '">'
                +   '<div class="aeo-visibility-list-copy">'
                +     '<strong>' + pageHtml + '</strong>'
                +     (meta ? '<span>' + esc(meta) + '</span>' : '')
                +     (citation.snippet ? '<em>' + esc(citation.snippet) + '</em>' : '')
                +   '</div>'
                +   '<span class="aeo-visibility-list-count">' + esc(citation.engine || 'AI Engine') + '</span>'
                + '</div>';
        }).join('') + '</div>';
    }

    function renderVisibilityCompetitorGrid(snapshot) {
        if (!snapshot || !snapshot.competitors || !snapshot.competitors.length) {
            return '<div class="aeo-visibility-empty"><strong>No competitor snapshot yet.</strong><span>Competitor visibility data appears as soon as Studio compares this site against the surrounding set.</span></div>';
        }

        return '<div class="aeo-visibility-competitor-grid">' + snapshot.competitors.slice(0, 6).map(function (competitor) {
            var scoreValue = hasNumber(competitor.visibilityScore) ? Math.round(competitor.visibilityScore) : '—';
            var deltaValue = competitor.delta30 !== null ? formatSignedDelta(competitor.delta30) : '—';
            var shareValue = competitor.citationShare !== null ? Math.round(competitor.citationShare) + '%' : '—';
            var mentionValue = hasNumber(competitor.mention_count) ? formatCompactNumber(competitor.mention_count) : '—';
            var tone = (hasNumber(snapshot.score) && hasNumber(competitor.visibilityScore) && competitor.visibilityScore >= snapshot.score + 10) ? 'critical' : 'neutral';
            var primaryLabel = hasNumber(competitor.visibilityScore) ? 'Visibility' : 'Mentions';
            var primaryValue = hasNumber(competitor.visibilityScore) ? scoreValue : mentionValue;

            return ''
                + '<article class="aeo-visibility-competitor-card">'
                +   '<div class="aeo-visibility-competitor-head">'
                +     '<h4>' + esc(competitor.name || 'Competitor') + '</h4>'
                +     '<span class="aeo-status-chip aeo-status-chip-' + esc(tone) + '">' + (tone === 'critical' ? 'Ahead' : 'Observed') + '</span>'
                +   '</div>'
                +   '<div class="aeo-visibility-competitor-metrics">'
                +     '<div><span>' + esc(primaryLabel) + '</span><strong>' + esc(String(primaryValue)) + '</strong></div>'
                +     '<div><span>30d delta</span><strong>' + esc(String(deltaValue)) + '</strong></div>'
                +     '<div><span>Citation share</span><strong>' + esc(String(shareValue)) + '</strong></div>'
                +   '</div>'
                + '</article>';
        }).join('') + '</div>';
    }

    function renderVisibilityStat(label, value, detail, tone) {
        return ''
            + '<div class="aeo-visibility-stat aeo-visibility-stat-' + esc(tone || 'neutral') + '">'
            +   '<span>' + esc(label) + '</span>'
            +   '<strong>' + esc(String(value == null ? '—' : value)) + '</strong>'
            +   (detail ? '<em>' + esc(detail) + '</em>' : '')
            + '</div>';
    }

    function renderVisibilityOverview(snapshot) {
        if (!snapshot || !snapshot.available) {
            return renderVisibilityEmptyState(
                'AI visibility is not ready yet',
                visibilityUiState.message || 'Run the first visibility sync in AEO admin to populate this stage.',
                snapshot
            );
        }

        var scoreTone = snapshot.score === null ? 'neutral' : (snapshot.score >= 70 ? 'healthy' : snapshot.score >= 50 ? 'warning' : 'critical');
        var summary = ''
            + '<div class="aeo-visibility-summary">'
            +   '<div class="aeo-visibility-score">'
            +     '<span class="aeo-visibility-score-label">Visibility score</span>'
            +     '<strong>' + esc(snapshot.score !== null ? Math.round(snapshot.score) : '—') + '</strong>'
            +     '<em>' + esc(snapshot.lastSyncedAt ? 'Last synced ' + formatDate(snapshot.lastSyncedAt) : 'Awaiting refresh timestamp') + '</em>'
            +   '</div>'
            +   '<div class="aeo-visibility-stat-grid">'
            +     renderVisibilityStat('7d delta', snapshot.delta7 !== null ? formatSignedDelta(snapshot.delta7) : '—', snapshot.delta7 > 0 ? 'Momentum is positive' : snapshot.delta7 < 0 ? 'Attention needed' : 'Stable', snapshot.delta7 > 0 ? 'healthy' : snapshot.delta7 < 0 ? 'critical' : 'neutral')
            +     renderVisibilityStat('30d delta', snapshot.delta30 !== null ? formatSignedDelta(snapshot.delta30) : '—', snapshot.delta30 > 0 ? 'Month-over-month lift' : snapshot.delta30 < 0 ? 'Month-over-month decline' : 'Flat trend', snapshot.delta30 > 0 ? 'healthy' : snapshot.delta30 < 0 ? 'critical' : 'neutral')
            +     renderVisibilityStat('Citations', formatCompactNumber(snapshot.citationsCount), snapshot.engineCount + ' engines captured', snapshot.citationsCount > 0 ? 'healthy' : 'neutral')
            +     renderVisibilityStat('Critical alerts', formatCompactNumber(snapshot.criticalCount), snapshot.isStale ? 'Snapshot is stale' : 'Current sync freshness looks good', snapshot.criticalCount > 0 ? 'critical' : 'healthy')
            +   '</div>'
            + '</div>';

        return ''
            + '<div class="aeo-visibility-grid">'
            +   renderVisibilityCard('Visibility snapshot', 'The fastest read on current AI presence, momentum, and sync freshness.', summary + renderVisibilitySparkline(snapshot), 'aeo-visibility-card-highlight aeo-visibility-card-wide aeo-visibility-card-' + scoreTone)
            +   renderVisibilityCard('Critical alerts', 'What needs attention before the next visibility review.', renderVisibilityAlertList(snapshot))
            +   renderVisibilityCard('Engine coverage', 'Where citations are appearing across answer engines.', renderVisibilityEngineRows(snapshot))
            +   renderVisibilityCard('Top cited pages', 'Pages that are earning the most AI citations right now.', renderVisibilityTopPages(snapshot))
            +   renderVisibilityAdminCard(snapshot)
            + '</div>';
    }

    function renderVisibilityCitations(snapshot) {
        if (!snapshot || !snapshot.available) {
            return renderVisibilityEmptyState(
                'Citations are not available yet',
                visibilityUiState.message || 'Studio will populate recent mentions here after the first visibility refresh.',
                snapshot
            );
        }

        return ''
            + '<div class="aeo-visibility-grid aeo-visibility-grid-compact">'
            +   renderVisibilityCard('Recent citations', 'Latest captured mentions, quoted pages, and engine sources.', renderVisibilityCitationRows(snapshot), 'aeo-visibility-card-wide')
            +   renderVisibilityAdminCard(snapshot, true)
            + '</div>';
    }

    function renderVisibilityCompetitors(snapshot) {
        if (!snapshot || !snapshot.available) {
            return renderVisibilityEmptyState(
                'Competitor visibility is not available yet',
                visibilityUiState.message || 'Studio will populate the competitor set here after comparison data is ready.',
                snapshot
            );
        }

        return ''
            + '<div class="aeo-visibility-grid aeo-visibility-grid-compact">'
            +   renderVisibilityCard('Competitor set', 'Sites currently being compared against this domain inside the visibility workspace.', renderVisibilityCompetitorGrid(snapshot), 'aeo-visibility-card-wide')
            +   renderVisibilityCard('Pressure points', 'Competitors far ahead of the site become the most urgent visibility gap to close.', renderVisibilityAlertList({
                    alerts: snapshot.competitors.filter(function (competitor) {
                        return hasNumber(snapshot.score) && hasNumber(competitor.visibilityScore) && competitor.visibilityScore >= snapshot.score + 10;
                    }).slice(0, 4).map(function (competitor) {
                        return {
                            severity: 'critical',
                            title: competitor.name + ' is ahead on visibility',
                            detail: 'Score ' + Math.round(competitor.visibilityScore) + ' vs ' + Math.round(snapshot.score || 0)
                        };
                    }),
                    isStale: false
                }))
            +   renderVisibilityAdminCard(snapshot, true)
            + '</div>';
    }

    function renderVisibilityTrends(snapshot) {
        if (!snapshot || !snapshot.available) {
            return renderVisibilityEmptyState(
                'Trend data is not available yet',
                visibilityUiState.message || 'Studio will populate trend history here after score snapshots accumulate.',
                snapshot
            );
        }

        var trendBody = ''
            + '<div class="aeo-visibility-summary aeo-visibility-summary-tight">'
            +   '<div class="aeo-visibility-stat-grid">'
            +     renderVisibilityStat('7d', snapshot.delta7 !== null ? formatSignedDelta(snapshot.delta7) : '—', 'Short-term momentum', snapshot.delta7 > 0 ? 'healthy' : snapshot.delta7 < 0 ? 'critical' : 'neutral')
            +     renderVisibilityStat('30d', snapshot.delta30 !== null ? formatSignedDelta(snapshot.delta30) : '—', 'Longer-term trajectory', snapshot.delta30 > 0 ? 'healthy' : snapshot.delta30 < 0 ? 'critical' : 'neutral')
            +     renderVisibilityStat('Trend flags', snapshot.trendIssueCount, snapshot.isStale ? 'Includes stale sync warning' : 'Derived from visibility deltas', snapshot.trendIssueCount > 0 ? 'critical' : 'healthy')
            +   '</div>'
            + '</div>'
            + renderVisibilitySparkline(snapshot);

        return ''
            + '<div class="aeo-visibility-grid aeo-visibility-grid-compact">'
            +   renderVisibilityCard('Visibility trend', 'How the visibility score is moving over time.', trendBody, 'aeo-visibility-card-wide')
            +   renderVisibilityCard('Trend alerts', 'Negative swings and stale syncs that need review.', renderVisibilityAlertList(snapshot))
            +   renderVisibilityAdminCard(snapshot, true)
            + '</div>';
    }

    function renderVisibility(snapshot) {
        var normalized = buildVisibilitySnapshot(snapshot);
        currentVisibilityPayload = normalized;
        var panels = {
            'visibility-overview': renderVisibilityOverview(normalized),
            'visibility-citations': renderVisibilityCitations(normalized),
            'visibility-competitors': renderVisibilityCompetitors(normalized),
            'visibility-trends': renderVisibilityTrends(normalized)
        };

        Object.keys(panels).forEach(function (tabId) {
            var panel = document.getElementById('tab-' + tabId);
            if (panel) panel.innerHTML = panels[tabId];
        });
        refreshWorkflowChrome();
    }

    function setVisibilityTabsLoading(message) {
        VISIBILITY_TAB_IDS.forEach(function (tabId) {
            var panel = document.getElementById('tab-' + tabId);
            if (panel) panel.innerHTML = renderVisibilityLoading(message);
        });
    }

    /* ── Workflow Stage Chrome ───────────────────────── */

    function getStageDescription(stageId) {
        switch (stageId) {
            case 'connect':
                return 'Configure the WordPress connection, confirm readiness, and review discovery findings before moving into diagnosis.';
            case 'diagnose':
                return 'Understand what is dragging AI visibility down at both the site level and the page level.';
            case 'fix':
                return 'Prioritize the most valuable actions, then move straight into the pages and guidance that support each fix.';
            case 'visibility':
                return 'Monitor AI citations, engine coverage, competitors, and trend movement. Operational logs now live in the AEO admin workspace.';
            default:
                return '';
        }
    }

    function getEnabledFeatureCount() {
        var checked = wrap.querySelectorAll('input[name="aeocas_enabled_features[]"]:checked').length;
        if (checked) return checked;
        var fallback = parseInt(wrap.getAttribute('data-feature-count') || '0', 10);
        return isNaN(fallback) ? 0 : fallback;
    }

    function getWeakestScorecardCategory(audit) {
        var scorecard = (audit && audit.scorecard) || [];
        if (!scorecard.length) return null;
        var cats = getCategories(scorecard);
        var weakest = null;

        cats.forEach(function (cat) {
            var scores = [];
            scorecard.forEach(function (item) {
                if (cat.ids.indexOf(item.id) !== -1 && typeof item.score === 'number') {
                    scores.push(item.score);
                }
            });
            if (!scores.length) return;
            var avg = Math.round(scores.reduce(function (sum, score) { return sum + score; }, 0) / scores.length);
            if (!weakest || avg < weakest.score) {
                weakest = {
                    label: cat.label,
                    score: avg,
                    color: cat.color,
                    bg: cat.bg
                };
            }
        });

        return weakest;
    }

    function getAveragePageScore(pages) {
        var scores = (pages || []).map(getPageScore).filter(function (score) { return score > 0; });
        if (!scores.length) return 0;
        return Math.round(scores.reduce(function (sum, score) { return sum + score; }, 0) / scores.length);
    }

    function getDiscoveryStatusLabel(context) {
        if (context.discovery && context.discovery.status === 'failed') return 'Failed';
        if (context.discovery && context.discovery.discovery) return 'Ready';
        if (discoveryUiState.phase === 'error') return 'Failed';
        if (discoveryUiState.phase === 'pending' || discoveryUiState.phase === 'loading') return 'Running';
        if (context.connected) return 'Queued';
        return 'Waiting';
    }

    function buildWorkflowContext() {
        var audit = currentAuditData;
        var discovery = currentDiscoveryPayload;
        var discoveryData = discovery && discovery.discovery ? discovery.discovery : null;
        var deterministicProfile = discoveryData && discoveryData.deterministic_profile ? discoveryData.deterministic_profile : {};
        var pages = (audit && audit.pages_reviewed) || [];
        var scorecard = (audit && audit.scorecard) || [];
        var opportunityModels = audit ? buildOpportunityModels(audit) : [];
        var rewriteCandidates = audit ? buildRewriteCandidates(audit) : [];
        var visibility = buildVisibilitySnapshot(currentVisibilityPayload || extractVisibilityPayload(audit));
        var uniqueOpportunityPages = {};
        var topicSignals = mergeArrays(
            deterministicProfile.topic_phrases,
            discoveryData && discoveryData.content_themes,
            discoveryData && discoveryData.topic_phrases
        );
        var entities = mergeArrays(
            deterministicProfile.entities,
            discoveryData && discoveryData.entities
        );

        opportunityModels.forEach(function (model) {
            model.relatedPages.forEach(function (page) {
                if (page && page.url) uniqueOpportunityPages[page.url] = 1;
            });
        });

        return {
            connected: wrap.getAttribute('data-connected') === '1' || !!wrap.querySelector('.aeo-status-bar.aeo-connected'),
            featureCount: getEnabledFeatureCount(),
            audit: audit,
            discovery: discovery,
            discoveryData: discoveryData,
            deterministicProfile: deterministicProfile,
            pages: pages,
            scorecard: scorecard,
            opportunityModels: opportunityModels,
            rewriteCandidates: rewriteCandidates,
            scorecardCritical: scorecard.filter(function (item) { return isCriticalScorecardItem(item); }).length,
            scorecardModerate: scorecard.filter(function (item) {
                var status = String(item.status || '').toLowerCase();
                return ['partial', 'moderate'].indexOf(status) !== -1;
            }).length,
            pageCritical: pages.filter(pageHasCriticalIssue).length,
            opportunityCritical: opportunityModels.filter(function (model) { return model.isCritical; }).length,
            rewriteCritical: rewriteCandidates.filter(function (candidate) { return candidate.tier === 'high'; }).length,
            quickWins: opportunityModels.filter(function (model) { return String(model.effort || '').toLowerCase() === 'low'; }).length,
            uniqueOpportunityPagesCount: Object.keys(uniqueOpportunityPages).length,
            avgPageScore: getAveragePageScore(pages),
            weakestPillar: getWeakestScorecardCategory(audit),
            discoveryStatusLabel: '',
            discoveryPagesCount: firstNonEmpty(
                pages.length ? pages.length : null,
                Array.isArray(deterministicProfile.page_titles) ? deterministicProfile.page_titles.length : null,
                typeof deterministicProfile.sitemap_url_count === 'number' ? deterministicProfile.sitemap_url_count : null,
                0
            ) || 0,
            topicSignalCount: topicSignals.length,
            entityCount: entities.length,
            competitorCount: discoveryData && Array.isArray(discoveryData.competitors) ? discoveryData.competitors.length : 0,
            visibility: visibility
        };
    }

    function renderStatusChip(label, tone) {
        return '<span class="aeo-status-chip aeo-status-chip-' + esc(tone || 'idle') + '">' + esc(label || 'Waiting') + '</span>';
    }

    function renderMetricCard(metric) {
        return ''
            + '<div class="aeo-stage-metric aeo-stage-metric-' + esc(metric.tone || 'neutral') + '">'
            +   '<div class="aeo-stage-metric-label">' + esc(metric.label || '') + '</div>'
            +   '<div class="aeo-stage-metric-value">' + esc(String(metric.value == null ? '—' : metric.value)) + '</div>'
            +   '<div class="aeo-stage-metric-detail">' + esc(metric.detail || '') + '</div>'
            + '</div>';
    }

    function getStageState(stageId, context) {
        var fixCritical = context.opportunityCritical + context.rewriteCritical;
        var discoveryFailed = (context.discovery && context.discovery.status === 'failed') || discoveryUiState.phase === 'error';
        var visibility = context.visibility || buildVisibilitySnapshot();

        switch (stageId) {
            case 'connect':
                if (!context.connected) return { tone: 'attention', label: 'Needs setup' };
                if (discoveryFailed) return { tone: 'attention', label: 'Discovery failed' };
                if (!context.discoveryData && (discoveryUiState.phase === 'pending' || discoveryUiState.phase === 'loading')) {
                    return { tone: 'progress', label: 'Discovering' };
                }
                if (!context.discoveryData) return { tone: 'progress', label: 'Connected' };
                if (context.discoveryData) return { tone: 'healthy', label: 'Ready' };
            case 'diagnose':
                if (!context.audit) return { tone: context.connected ? 'progress' : 'idle', label: context.connected ? 'Waiting' : 'Blocked' };
                if (context.scorecardCritical + context.pageCritical > 0) return { tone: 'attention', label: 'Needs attention' };
                if (context.scorecardModerate > 0) return { tone: 'progress', label: 'In progress' };
                return { tone: 'healthy', label: 'Healthy' };
            case 'fix':
                if (!context.audit) return { tone: context.connected ? 'progress' : 'idle', label: context.connected ? 'Waiting' : 'Blocked' };
                if (fixCritical > 0) return { tone: 'attention', label: 'Needs action' };
                if (context.opportunityModels.length || context.rewriteCandidates.length) return { tone: 'progress', label: 'In progress' };
                return { tone: 'healthy', label: 'Healthy' };
            case 'visibility':
                if (!context.connected) return { tone: 'idle', label: 'Blocked' };
                if (visibilityUiState.phase === 'loading' || visibilityUiState.phase === 'refreshing') return { tone: 'progress', label: 'Syncing' };
                if (visibilityUiState.phase === 'error' && !visibility.available) return { tone: 'attention', label: 'Needs refresh' };
                if (!visibility.available) return { tone: 'progress', label: 'Waiting' };
                if (visibility.criticalCount > 0) return { tone: 'attention', label: 'Needs attention' };
                if ((visibility.warningAlerts || 0) > 0 || (visibility.delta7 !== null && visibility.delta7 < 0) || (visibility.delta30 !== null && visibility.delta30 < 0)) {
                    return { tone: 'progress', label: 'Monitoring' };
                }
                return { tone: 'healthy', label: 'Visible' };
            default:
                return { tone: 'idle', label: 'Waiting' };
        }
    }

    function getStageContextLine(stageId, context) {
        var urgentDiagnose = context.scorecardCritical + context.pageCritical;
        var urgentFix = context.opportunityCritical + context.rewriteCritical;
        var discoveryFailed = (context.discovery && context.discovery.status === 'failed') || discoveryUiState.phase === 'error';
        var visibility = context.visibility || buildVisibilitySnapshot();

        switch (stageId) {
            case 'connect':
                if (!context.connected) {
                    return 'Finish this step first so discovery, diagnosis, and fixes can populate.';
                }
                if (discoveryFailed) {
                    return 'The site is connected, but the latest discovery pass failed and needs a clean rerun.';
                }
                return context.discoveryData
                    ? 'Discovery surfaced ' + context.discoveryPagesCount + ' pages, ' + context.topicSignalCount + ' topic signals, and ' + context.entityCount + ' entities.'
                    : 'The deterministic profile is still building. Results appear here as soon as the remote worker finishes.';
            case 'diagnose':
                return context.audit
                    ? urgentDiagnose + ' critical issues are currently dragging the score down across criteria and pages.'
                    : 'Diagnosis will populate automatically once the site audit completes.';
            case 'fix':
                return context.audit
                    ? urgentFix + ' urgent fixes, ' + context.quickWins + ' quick wins, and ' + context.rewriteCandidates.length + ' rewrite candidates are ready now.'
                    : 'Opportunity intelligence appears here after the first audit run completes.';
            case 'visibility':
                if (!context.connected) {
                    return 'Visibility remains blocked until the site connection is complete.';
                }
                if (!visibility.available) {
                    return visibilityUiState.message
                        ? visibilityUiState.message
                        : 'Visibility insights appear here after Studio finishes the first sync. Full logs now live in AEO admin.';
                }
                return 'Visibility score ' + (visibility.score !== null ? Math.round(visibility.score) : '—') + ' with '
                    + visibility.citationsCount + ' citations across ' + visibility.engineCount + ' engines'
                    + (visibility.lastSyncedAt ? ', last synced ' + formatDate(visibility.lastSyncedAt) + '.' : '.');
            default:
                return '';
        }
    }

    function getNextBestAction(stageId, context) {
        if (stageId === 'connect') {
            if (!context.connected) {
                return {
                    title: 'Finish the connection setup',
                    body: 'Use the connection controls below so the first discovery and audit runs can complete.',
                    ctaLabel: '',
                    targetTab: ''
                };
            }
            if ((context.discovery && context.discovery.status === 'failed') || discoveryUiState.phase === 'error') {
                return {
                    title: 'Review discovery and rerun the audit',
                    body: 'The connection is healthy, but the latest discovery pass failed. Open Discovery and retry the pipeline cleanly.',
                    ctaLabel: 'Open Discovery',
                    targetTab: 'discovery'
                };
            }
            if (!context.discoveryData) {
                return {
                    title: 'Watch discovery populate',
                    body: 'The site is connected. Review the extracted profile as soon as the first discovery pass finishes.',
                    ctaLabel: 'Open Discovery',
                    targetTab: 'discovery'
                };
            }
            return {
                title: 'Start diagnosing the biggest score drag',
                body: 'Connection is healthy. The next step is understanding which criteria and pages need attention first.',
                ctaLabel: 'Open Diagnose',
                targetTab: 'scoreboard'
            };
        }

        if (stageId === 'diagnose') {
            if (!context.audit) {
                return {
                    title: 'Wait for audit data to arrive',
                    body: 'The system is still scoring the site. Diagnose will populate with criteria and pages as soon as the audit completes.',
                    ctaLabel: 'Open Discover',
                    targetTab: 'discovery'
                };
            }
            if (context.scorecardCritical >= context.pageCritical && context.scorecardCritical > 0) {
                return {
                    title: 'Start with the Site Audit view',
                    body: 'Criterion-level failures reveal the structural issues that are affecting the whole site.',
                    ctaLabel: 'Open Site Audit',
                    targetTab: 'scoreboard'
                };
            }
            if (context.pageCritical > 0) {
                return {
                    title: 'Inspect the Pages Audit next',
                    body: 'Page-level triage shows exactly which URLs are clustering the highest-risk issues.',
                    ctaLabel: 'Open Pages Audit',
                    targetTab: 'site-audit'
                };
            }
            return {
                title: 'Diagnosis is stable; move into fixes',
                body: 'The next best step is to work the highest-impact opportunities and rewrite candidates.',
                ctaLabel: 'Open Fix',
                targetTab: 'opportunities'
            };
        }

        if (stageId === 'fix') {
            if (!context.audit) {
                return {
                    title: 'Wait for fixes to populate',
                    body: 'Opportunities and rewrite candidates are generated from the first completed audit.',
                    ctaLabel: 'Open Discover',
                    targetTab: 'discovery'
                };
            }
            if (context.opportunityCritical > 0) {
                return {
                    title: 'Work the top opportunities first',
                    body: 'These pair the clearest lift with linked pages, knowledge guides, and FAQs for fast execution.',
                    ctaLabel: 'Open Opportunities',
                    targetTab: 'opportunities'
                };
            }
            if (context.rewriteCandidates.length > 0) {
                return {
                    title: 'Queue the lowest-ranking pages',
                    body: 'The next wave of lift is likely to come from rewriting the weakest pages in the queue.',
                    ctaLabel: 'Open Rewrite Queue',
                    targetTab: 'rewrite'
                };
            }
            return {
                title: 'Start monitoring visibility lift',
                body: 'The urgent fixes are under control. Watch citations and trend movement to confirm the work is improving AI presence.',
                ctaLabel: 'Open AI Visibility',
                targetTab: 'visibility-overview'
            };
        }

        if (!context.connected) {
            return {
                title: 'Complete the site connection first',
                body: 'Visibility insights only populate after the site is connected and Studio can sync the domain.',
                ctaLabel: 'Open Connect',
                targetTab: 'connect'
            };
        }

        if (!context.visibility.available) {
            return {
                title: 'Open the admin workspace',
                body: 'The plugin is waiting for the latest visibility snapshot. Studio is the place to inspect sync status and logs.',
                ctaLabel: 'Open Full Admin',
                href: getVisibilityAdminUrl(context.visibility)
            };
        }
        if (context.visibility.criticalCount > 0) {
            return {
                title: 'Review critical visibility alerts',
                body: 'The latest snapshot shows urgent issues or stale data. Start in Overview and confirm the next sync in admin if needed.',
                ctaLabel: 'Open Overview',
                targetTab: 'visibility-overview'
            };
        }
        if (context.visibility.competitorThreatCount > 0) {
            return {
                title: 'Inspect the competitor gap',
                body: 'Competitors are outpacing the site on visibility. Use the competitor view to see where the pressure is highest.',
                ctaLabel: 'Open Competitors',
                targetTab: 'visibility-competitors'
            };
        }
        if (context.visibility.trendIssueCount > 0) {
            return {
                title: 'Review the recent trend drop',
                body: 'The short-term or monthly visibility delta slipped below the healthy range.',
                ctaLabel: 'Open Trends',
                targetTab: 'visibility-trends'
            };
        }
        if (context.visibility.citations.length > 0) {
            return {
                title: 'Check the latest citations',
                body: 'Recent engine mentions are flowing in. Review which pages and queries are earning them.',
                ctaLabel: 'Open Citations',
                targetTab: 'visibility-citations'
            };
        }
        return {
            title: 'Stay close to the visibility workspace',
            body: 'Use this stage to monitor score movement, citations, and competitors while deeper logs remain in admin.',
            ctaLabel: 'Open Full Admin',
            href: getVisibilityAdminUrl(context.visibility)
        };
    }

    function buildStageMetrics(stageId, context) {
        var weakest = context.weakestPillar;
        var visibility = context.visibility || buildVisibilitySnapshot();

        switch (stageId) {
            case 'connect':
                return [
                    { label: 'Connection', value: context.connected ? 'Connected' : 'Action needed', detail: context.connected ? 'Platform link is healthy' : 'Setup is blocking the workflow', tone: context.connected ? 'healthy' : 'critical' },
                    { label: 'Discovery status', value: getDiscoveryStatusLabel(context), detail: context.discovery && context.discovery.current_stage ? context.discovery.current_stage : 'Remote worker state', tone: getStageState('connect', context).tone },
                    { label: 'Pages surfaced', value: context.discoveryPagesCount, detail: 'URLs discovered for the latest audit', tone: 'neutral' },
                    { label: 'Topic signals', value: context.topicSignalCount, detail: context.competitorCount + ' competitors and ' + context.entityCount + ' entities identified', tone: 'neutral' },
                    { label: 'Features enabled', value: context.featureCount, detail: 'Modules active on this site', tone: 'neutral' }
                ];
            case 'diagnose':
                return [
                    { label: 'Critical criteria', value: context.scorecardCritical, detail: 'Site-level scorecard failures', tone: context.scorecardCritical > 0 ? 'critical' : 'healthy' },
                    { label: 'Critical pages', value: context.pageCritical, detail: 'Pages with high-risk issues', tone: context.pageCritical > 0 ? 'critical' : 'healthy' },
                    { label: 'Avg AEO Rank', value: context.avgPageScore || '—', detail: 'Average across reviewed pages', tone: context.avgPageScore > 0 ? (context.avgPageScore >= 70 ? 'healthy' : context.avgPageScore >= 50 ? 'warning' : 'critical') : 'neutral' },
                    { label: 'Weakest pillar', value: weakest ? weakest.label : '—', detail: weakest ? weakest.score + '/10 average score' : 'Awaiting site audit data', tone: weakest ? (weakest.score >= 7 ? 'healthy' : weakest.score >= 5 ? 'warning' : 'critical') : 'neutral' }
                ];
            case 'fix':
                return [
                    { label: 'Critical now', value: context.opportunityCritical + context.rewriteCritical, detail: 'Urgent items across opportunities and rewrites', tone: (context.opportunityCritical + context.rewriteCritical) > 0 ? 'critical' : 'healthy' },
                    { label: 'Quick wins', value: context.quickWins, detail: 'Low-effort opportunities', tone: context.quickWins > 0 ? 'healthy' : 'neutral' },
                    { label: 'Rewrite queue', value: context.rewriteCandidates.length, detail: 'Pages currently flagged for rewrite', tone: context.rewriteCandidates.length > 0 ? 'warning' : 'neutral' },
                    { label: 'High-leverage pages', value: context.uniqueOpportunityPagesCount, detail: 'URLs linked to the top opportunities', tone: context.uniqueOpportunityPagesCount > 0 ? 'neutral' : 'healthy' }
                ];
            case 'visibility':
                return [
                    { label: 'Visibility score', value: visibility.score !== null ? Math.round(visibility.score) : '—', detail: visibility.lastSyncedAt ? 'Last synced ' + formatDate(visibility.lastSyncedAt) : 'Awaiting sync timestamp', tone: visibility.score !== null ? (visibility.score >= 70 ? 'healthy' : visibility.score >= 50 ? 'warning' : 'critical') : 'neutral' },
                    { label: '7d delta', value: visibility.delta7 !== null ? formatSignedDelta(visibility.delta7) : '—', detail: 'Short-term score movement', tone: visibility.delta7 !== null ? (visibility.delta7 > 0 ? 'healthy' : visibility.delta7 < 0 ? 'critical' : 'neutral') : 'neutral' },
                    { label: '30d delta', value: visibility.delta30 !== null ? formatSignedDelta(visibility.delta30) : '—', detail: 'Month-over-month trend', tone: visibility.delta30 !== null ? (visibility.delta30 > 0 ? 'healthy' : visibility.delta30 < 0 ? 'critical' : 'neutral') : 'neutral' },
                    { label: 'Citations', value: visibility.citationsCount, detail: visibility.engineCount + ' engines currently contributing', tone: visibility.citationsCount > 0 ? 'healthy' : 'neutral' },
                    { label: 'Critical alerts', value: visibility.criticalCount, detail: visibility.isStale ? 'Snapshot is stale' : 'Logs moved to admin', tone: visibility.criticalCount > 0 ? 'critical' : 'healthy' }
                ];
            default:
                return [];
        }
    }

    function renderNextActionCard(action) {
        if (!action) return '';
        var buttonHtml = '';
        if (action.ctaLabel && action.targetTab) {
            buttonHtml = '<a href="#" class="button button-primary aeo-stage-nav-link" data-target-tab="' + esc(action.targetTab) + '">' + esc(action.ctaLabel) + '</a>';
        } else if (action.ctaLabel && action.href) {
            buttonHtml = '<a href="' + esc(action.href) + '" class="button button-primary" target="_blank" rel="noopener">' + esc(action.ctaLabel) + '</a>';
        }

        return ''
            + '<aside class="aeo-next-action">'
            +   '<div class="aeo-next-action-kicker">Next best action</div>'
            +   '<h3 class="aeo-next-action-title">' + esc(action.title || '') + '</h3>'
            +   '<p class="aeo-next-action-body">' + esc(action.body || '') + '</p>'
            +   buttonHtml
            + '</aside>';
    }

    function renderStageHero(stageId, context) {
        var stage = STAGE_BY_ID[stageId];
        if (!stage) return '';

        var state = getStageState(stageId, context);
        var action = getNextBestAction(stageId, context);

        return ''
            + '<div class="aeo-stage-hero-main">'
            +   '<div class="aeo-stage-kicker">Step ' + stage.order + ' of ' + STAGE_CONFIGS.length + '</div>'
            +   '<div class="aeo-stage-title-row">'
            +     '<h2 class="aeo-stage-title">' + esc(stage.title) + '</h2>'
            +     renderStatusChip(state.label, state.tone)
            +   '</div>'
            +   '<p class="aeo-stage-description">' + esc(getStageDescription(stageId)) + '</p>'
            +   '<p class="aeo-stage-context">' + esc(getStageContextLine(stageId, context)) + '</p>'
            + '</div>'
            + renderNextActionCard(action);
    }

    function renderStageSummary(stageId, context) {
        var metrics = buildStageMetrics(stageId, context);
        if (!metrics.length) return '';
        return metrics.map(renderMetricCard).join('');
    }

    function applyWorkflowStepState(stageId, state) {
        var step = getPrimaryStep(stageId);
        if (!step) return;
        step.classList.remove('is-healthy', 'is-attention', 'is-progress', 'is-idle');
        step.classList.add('is-' + (state.tone || 'idle'));
        var stateEl = step.querySelector('.aeo-workflow-step-state');
        if (stateEl) {
            stateEl.textContent = state.label || 'Waiting';
            stateEl.className = 'aeo-workflow-step-state is-' + (state.tone || 'idle');
        }
    }

    function refreshWorkflowChrome() {
        var context = buildWorkflowContext();
        STAGE_CONFIGS.forEach(function (stage) {
            var hero = document.getElementById('aeo-stage-hero-' + stage.id);
            var summary = document.getElementById('aeo-stage-summary-' + stage.id);
            if (hero) hero.innerHTML = renderStageHero(stage.id, context);
            if (summary) summary.innerHTML = renderStageSummary(stage.id, context);
            applyWorkflowStepState(stage.id, getStageState(stage.id, context));
        });
        updateWorkflowBadges(context);
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
        var embeddedVisibility = extractVisibilityPayload(audit);
        if (embeddedVisibility && typeof embeddedVisibility === 'object') {
            visibilityUiState.phase = 'ready';
            visibilityUiState.message = '';
            renderVisibility(embeddedVisibility);
        }
        refreshSiteAuditCount();
        refreshWorkflowChrome();
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

    var PAGE_RANK_PILLAR_LABELS = [
        { key: 'contentOriginality',  label: 'Content Originality',  weight: '25%' },
        { key: 'contentUniqueness',   label: 'Content Uniqueness',   weight: '25%' },
        { key: 'extractability',      label: 'Extractability',       weight: '25%' },
        { key: 'entityDataRichness',  label: 'Entity & Data Richness', weight: '15%' },
        { key: 'structuralSignals',   label: 'Structural Signals',   weight: '10%' }
    ];

    function findPageByUrl(url) {
        if (!currentAuditData || !url) return null;
        var pages = currentAuditData.pages_reviewed || [];
        for (var i = 0; i < pages.length; i++) {
            if (pages[i] && pages[i].url === url) return pages[i];
        }
        return null;
    }

    function renderPageScoreBreakdown(page) {
        if (!page) return '<p>Page data not found.</p>';
        var score = getPageScore(page);
        var range = getScoreRangeLabel(score);
        var grade = page.pageRankGrade || '';
        var shortUrl = (page.url || '').replace(/^https?:\/\/[^/]+/, '') || (page.url || '');

        var html = '';

        // Header: URL + title + score + range
        html += '<div class="aeo-score-modal-hero">';
        html +=   '<div class="aeo-score-modal-score">';
        html +=     renderScoreCircle(score || 0, 100, 120);
        html +=   '</div>';
        html +=   '<div class="aeo-score-modal-range">';
        html +=     '<div class="aeo-score-modal-range-label" style="color:' + range.color + ';">' + esc(range.label) + (grade ? ' — Grade ' + esc(grade) : '') + '</div>';
        html +=     '<div style="font-weight:600;margin:6px 0 2px;font-size:14px;color:#1d2327;">' + esc(page.title || shortUrl) + '</div>';
        html +=     '<div style="font-size:12px;"><a href="' + esc(page.url) + '" target="_blank" rel="noopener" style="color:#646970;">' + esc(shortUrl) + ' &#8599;</a></div>';
        html +=   '</div>';
        html += '</div>';

        // Pillar breakdown — AEO Page Rank uses 5 pillars (see studio
        // PAGE_RANK_PILLAR_LABELS). Only show if data is present.
        var pillars = page.pageRankPillars || {};
        var hasPillars = PAGE_RANK_PILLAR_LABELS.some(function (p) { return pillars[p.key]; });
        if (hasPillars) {
            html += '<h3 class="aeo-score-modal-subhead">Pillar breakdown</h3>';
            html += '<div class="aeo-score-modal-pillars">';
            PAGE_RANK_PILLAR_LABELS.forEach(function (p) {
                var pillar = pillars[p.key];
                if (!pillar) return;
                var pScore = typeof pillar.score === 'number' ? pillar.score : 0;
                var pct = Math.max(0, Math.min(100, Math.round(pScore)));
                html += '<section class="aeo-score-pillar-card">';
                html +=   '<div class="aeo-score-pillar-head">';
                html +=     '<span class="aeo-cat-badge" style="background:#e8f0fe;color:#1967d2;">' + esc(p.label).toUpperCase() + '</span>';
                html +=     '<span class="aeo-score-pillar-weight">' + esc(p.weight) + ' of score</span>';
                html +=     '<span class="aeo-score-pillar-avg" style="color:' + scoreColor100(pct) + ';">' + pct + '/100</span>';
                html +=   '</div>';
                html +=   '<div class="aeo-score-pillar-bar"><div class="aeo-score-pillar-fill" style="width:' + pct + '%;background:' + scoreColor100(pct) + ';"></div></div>';
                var checks = Array.isArray(pillar.checks) ? pillar.checks : [];
                if (checks.length) {
                    html += '<ul class="aeo-score-pillar-criteria">';
                    checks.forEach(function (c) {
                        var cScore = typeof c.score === 'number' ? c.score : 0;
                        var clr = scoreColor10(cScore);
                        var bg  = scoreBg10(cScore);
                        var label = c.label || c.criterion || c.name || 'Check';
                        html += '<li>';
                        html +=   '<span class="aeo-score-pillar-crit-name">' + esc(label) + '</span>';
                        html +=   '<span class="aeo-score-badge-pill" style="background:' + bg + ';color:' + clr + ';">' + cScore + '/10</span>';
                        html += '</li>';
                    });
                    html += '</ul>';
                }
                html += '</section>';
            });
            html += '</div>';
        }

        // Criterion scores fallback (flat list, no pillar grouping)
        var critScores = Array.isArray(page.criterionScores) ? page.criterionScores : [];
        if (critScores.length && !hasPillars) {
            html += '<h3 class="aeo-score-modal-subhead">Criterion scores</h3>';
            html += '<ul class="aeo-score-pillar-criteria" style="background:#f9f9fa;padding:14px 18px;border-radius:8px;border:1px solid #f0f0f1;">';
            critScores.forEach(function (c) {
                var cScore = typeof c.score === 'number' ? c.score : 0;
                var clr = scoreColor10(cScore);
                var bg  = scoreBg10(cScore);
                html += '<li>';
                html +=   '<span class="aeo-score-pillar-crit-name">' + esc(c.criterion_label || c.criterion || 'Criterion') + '</span>';
                html +=   '<span class="aeo-score-badge-pill" style="background:' + bg + ';color:' + clr + ';">' + cScore + '/10</span>';
                html += '</li>';
            });
            html += '</ul>';
        }

        // Issues list
        var issues = Array.isArray(page.issues) ? page.issues : [];
        if (issues.length) {
            html += '<h3 class="aeo-score-modal-subhead">Issues (' + issues.length + ')</h3>';
            html += '<ul class="aeo-score-pillar-criteria" style="background:#fef7e0;padding:12px 18px;border-radius:8px;border:1px solid #f5d76e;">';
            issues.forEach(function (iss) {
                var sev = (iss.severity || '').toLowerCase();
                var sevColor = sev === 'high' || sev === 'critical' ? '#ea4335' : sev === 'medium' ? '#c5a200' : '#50575e';
                html += '<li>';
                html +=   '<span class="aeo-score-pillar-crit-name">' + esc(iss.label || iss.check || '') + '</span>';
                html +=   '<span class="aeo-score-badge-pill" style="background:#fff;color:' + sevColor + ';border:1px solid ' + sevColor + '66;">' + esc(iss.severity || 'issue') + '</span>';
                html += '</li>';
            });
            html += '</ul>';
        }

        // Strengths list
        var strengths = Array.isArray(page.strengths) ? page.strengths : [];
        if (strengths.length) {
            html += '<h3 class="aeo-score-modal-subhead">Strengths (' + strengths.length + ')</h3>';
            html += '<ul class="aeo-score-pillar-criteria" style="background:#e6f4ea;padding:12px 18px;border-radius:8px;border:1px solid #aedcbc;">';
            strengths.forEach(function (s) {
                html += '<li>';
                html +=   '<span class="aeo-score-pillar-crit-name">' + esc(s.label || s.check || '') + '</span>';
                html +=   '<span class="aeo-score-badge-pill" style="background:#fff;color:#137333;border:1px solid #34a85366;">ok</span>';
                html += '</li>';
            });
            html += '</ul>';
        }

        return html;
    }

    function openScoreModal(page) {
        if (!currentAuditData) return;
        var modal = document.getElementById('aeo-score-modal');
        var body  = document.getElementById('aeo-score-modal-body');
        var title = document.getElementById('aeo-score-modal-title');
        if (!modal || !body) return;

        if (page) {
            if (title) title.textContent = 'Page Score Breakdown — ' + (page.title || page.url || '');
            body.innerHTML = renderPageScoreBreakdown(page);
        } else {
            if (title) title.textContent = 'AEO Page Rank — Score Breakdown';
            body.innerHTML = renderScoreBreakdown(currentAuditData);
        }

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
                    if (checkAuthExpired(res)) return;
                    var msg = 'Your first site audit is still running.';
                    var code = '';
                    if (res.data) {
                        if (typeof res.data === 'string') msg = res.data;
                        else {
                            if (res.data.message) msg = res.data.message;
                            code = res.data.code || '';
                        }
                    }

                    if (currentAuditData) {
                        if (code === 'aeocas_no_audit') {
                            startAuditRetry();
                        } else {
                            showError(msg + ' Showing the last completed audit snapshot.');
                        }
                        refreshWorkflowChrome();
                        return;
                    }

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
                    refreshWorkflowChrome();
                }
            })
            .catch(function (err) {
                if (currentAuditData) {
                    showError('Network error: ' + (err.message || 'Please try again.') + ' Showing the last completed audit snapshot.');
                    refreshWorkflowChrome();
                    return;
                }
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
                refreshWorkflowChrome();
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
        currentDiscoveryPayload = payload || null;
        tab.innerHTML = renderDiscoveryPending(payload);
        startDiscoveryTicker();
        refreshWorkflowChrome();
    }

    function applyPendingUpdate(payload) {
        // Already showing the pending card — just patch the dynamic state and let
        // the ticker update the DOM. Avoids a full innerHTML replacement flash
        // and keeps the elapsed counter climbing smoothly.
        currentDiscoveryPayload = payload || currentDiscoveryPayload;
        var st = discoveryUiState;
        st.status       = (payload && payload.status) || 'pending';
        st.currentStage = (payload && payload.current_stage) || null;
        st.lastPollAt   = Date.now();
        updateDiscoveryPendingDynamic();
        // The Site Audit pending card mirrors discoveryUiState, so re-render
        // it if the audit data isn't in yet.
        if (!currentAuditData) setSiteAuditPending();
        refreshWorkflowChrome();
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
                    currentDiscoveryPayload = payload || null;
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
                        discoveryUiState.status = 'failed';
                        discoveryUiState.currentStage = (payload && payload.current_stage) || 'Audit failed.';
                        tab.innerHTML = renderDiscoveryFailed(payload);
                        setSiteAuditPending(); // will also show failed state via shared stage label
                    } else if (payload && payload.discovery) {
                        // Ready: stop everything and render full findings.
                        stopDiscoveryPolling();
                        stopDiscoveryTicker();
                        discoveryUiState.phase = 'ready';
                        discoveryUiState.status = (payload && payload.status) || 'completed';
                        discoveryUiState.currentStage = (payload && payload.current_stage) || 'Discovery complete.';
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
                    refreshWorkflowChrome();
                } else {
                    if (checkAuthExpired(res)) return;
                    var msg = (res.data && res.data.message) ? res.data.message : 'Failed to load discovery.';
                    var code = (res.data && res.data.code) || '';
                    if (code === 'aeocas_no_discovery') {
                        currentDiscoveryPayload = { status: 'pending', current_stage: 'Waiting for the audit job to be queued…' };
                        // No job row yet on the remote — probably the onboard insert
                        // hasn't landed yet, or the user arrived before connecting.
                        // Render the pending card and KEEP polling (this was the
                        // bug that made the UI look permanently stuck).
                        if (discoveryUiState.phase !== 'pending') {
                            renderPendingFresh(tab, currentDiscoveryPayload);
                        } else {
                            applyPendingUpdate(currentDiscoveryPayload);
                        }
                        startDiscoveryPolling();
                    } else {
                        stopDiscoveryPolling();
                        stopDiscoveryTicker();
                        discoveryUiState.phase = 'error';
                        discoveryUiState.status = 'failed';
                        discoveryUiState.currentStage = msg;
                        currentDiscoveryPayload = { status: 'failed', current_stage: msg };
                        tab.innerHTML = '<div class="notice notice-error" style="padding:12px 16px;"><p>' + esc(msg) + '</p></div>';
                    }
                    refreshWorkflowChrome();
                }
            })
            .catch(function () {
                // Network error — keep polling silently; the ticker keeps ticking
                // and "Last checked" will drift, making the stall visible.
            });
    }

    function loadVisibility(refresh) {
        if (!currentVisibilityPayload || !currentVisibilityPayload.available) {
            setVisibilityTabsLoading(refresh ? 'Refreshing AI visibility...' : 'Loading AI visibility...');
        }

        visibilityUiState.phase = refresh ? 'refreshing' : 'loading';
        visibilityUiState.message = '';
        refreshWorkflowChrome();

        var data = new FormData();
        data.append('action', 'aeocas_get_visibility');
        data.append('nonce', aeocasAudit.nonce);
        if (refresh) data.append('refresh', '1');

        fetch(aeocasAudit.ajaxUrl, { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    visibilityUiState.phase = 'ready';
                    visibilityUiState.message = '';
                    renderVisibility(res.data);
                    return;
                }

                if (checkAuthExpired(res)) return;

                var msg = (res.data && res.data.message) ? res.data.message : 'Unable to load AI visibility.';
                var code = (res.data && res.data.code) || '';

                if (code === 'aeocas_no_visibility') {
                    visibilityUiState.phase = 'empty';
                    visibilityUiState.message = msg;
                    if (currentVisibilityPayload && currentVisibilityPayload.available) {
                        renderVisibility(currentVisibilityPayload);
                    } else {
                        currentVisibilityPayload = null;
                        renderVisibility(null);
                    }
                    return;
                }

                visibilityUiState.phase = 'error';
                visibilityUiState.message = msg;
                if (currentVisibilityPayload && currentVisibilityPayload.available) {
                    showError(msg + ' Showing the last completed visibility snapshot.');
                    renderVisibility(currentVisibilityPayload);
                    return;
                }

                currentVisibilityPayload = null;
                renderVisibility(null);
            })
            .catch(function (err) {
                visibilityUiState.phase = 'error';
                visibilityUiState.message = 'Network error: ' + (err.message || 'Please try again.');
                if (currentVisibilityPayload && currentVisibilityPayload.available) {
                    showError(visibilityUiState.message + ' Showing the last completed visibility snapshot.');
                    renderVisibility(currentVisibilityPayload);
                    return;
                }

                currentVisibilityPayload = null;
                renderVisibility(null);
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
            if (!isConnected) {
                showError('Connect your site first to load audit, discovery, and visibility data.');
                activateTab('connect');
                return;
            }
            loadAudit(true);
            loadDiscovery(true);
            loadVisibility(true);
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
                        loadVisibility(true);
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
        if (!isConnected) {
            showError('Connect your site first before running a re-audit.');
            activateTab('connect');
            return;
        }

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
                    if (checkAuthExpired(res)) return;
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

    // Delegated handler for "Run Full Site Re-Audit" and "Re-run Discovery"
    // buttons inside tab panels. Both share the same backend trigger since
    // Discovery is a stage of the full audit pipeline.
    //
    // We deliberately do NOT switch the active tab — the user stays on
    // whatever tab they clicked from. The global progress bar at the top of
    // the wrap (#aeo-reaudit-progress) shows live stage updates, and the
    // Discovery tab's own pending card is repainted in the background so
    // it's ready when the user navigates there.
    wrap.addEventListener('click', function (e) {
        var target = e.target.closest && e.target.closest('.aeo-trigger-reaudit, .aeo-rerun-discovery');
        if (!target) return;
        e.preventDefault();
        if (pollTimer) return;

        // Force Discovery UI back into its "running" state so the pending
        // card paints immediately and the elapsed counter starts from zero.
        stopDiscoveryTicker();
        stopDiscoveryPolling();
        discoveryUiState.phase = 'idle';

        // Paint the pending card in the background (Discovery tab won't be
        // visible right now unless the user was already there).
        var discTab = document.getElementById('tab-discovery');
        if (discTab) {
            renderPendingFresh(discTab, { status: 'pending', current_stage: 'Waiting for the audit worker…' });
        }

        triggerReaudit();
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

    wrap.addEventListener('click', function (e) {
        var navLink = e.target.closest && e.target.closest('.aeo-stage-nav-link');
        if (!navLink) return;
        e.preventDefault();
        var targetTab = navLink.getAttribute('data-target-tab');
        if (targetTab) activateTab(targetTab);
    });

    // Delegated handler for clicking the AEO score (opens breakdown modal).
    // Covers both the overall-score circle (Connect/Overview) and per-page
    // score badges in the Pages Audit table.
    wrap.addEventListener('click', function (e) {
        var overall = e.target.closest && e.target.closest('.aeo-score-trigger');
        if (overall) {
            e.preventDefault();
            openScoreModal();
            return;
        }
        var pageBtn = e.target.closest && e.target.closest('.aeo-page-score-trigger');
        if (pageBtn) {
            e.preventDefault();
            var url = pageBtn.getAttribute('data-page-url');
            var page = findPageByUrl(url);
            if (page) openScoreModal(page);
            return;
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

    /**
     * Check if an AJAX response indicates the API key is dead. If so, force
     * a page reload to the Connect tab so the user sees the "Continue with
     * Google" reconnect flow. Returns true if auth expired (caller should
     * stop processing).
     */
    function checkAuthExpired(res) {
        var code = res && res.data && res.data.code;
        if (code === 'aeocas_auth_expired' || code === 'aeocas_no_key') {
            // Stop all polling so we don't spam dead requests.
            stopDiscoveryPolling();
            stopDiscoveryTicker();
            stopAuditRetry();
            if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
            // If we're already on the Connect tab, don't hard-refresh the page
            // back onto itself. Just surface the reconnect state in-place.
            if (getCurrentTabForStage('connect') === 'connect' && activeStageId === 'connect') {
                showError('Connect your site to continue.');
                activateTab('connect');
                return true;
            }
            // Redirect to the Connect tab which will show the reconnect UI
            // (the PHP already cleared the options server-side).
            window.location.href = window.location.pathname + '?page=aeocas-audit-report&tab=connect';
            return true;
        }
        return false;
    }

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

    initWorkflowRail();
    initStageSubtabs();
    initAccordions();

    // Honor ?tab=<slug> URL param and map legacy flat-tab slugs into the new
    // workflow stages. Grouped stages keep the child tab in the URL so deep
    // links continue to work.
    (function () {
        var requested = wrap.getAttribute('data-requested-tab') || '';
        if (!requested) {
            try {
                var params = new URLSearchParams(window.location.search);
                requested = params.get('tab') || '';
            } catch (e) { /* ignore */ }
        }
        if (!isConnected) {
            requested = 'connect';
        }
        activateTab(normalizeRequestedView(requested), true);
    })();

    refreshWorkflowChrome();
    if (!isConnected) {
        updateUrlTab('connect');
        return;
    }
    loadDiscovery(false);
    loadAudit(false);
    loadVisibility(false);
    loadLocalContentIndex();

})();
