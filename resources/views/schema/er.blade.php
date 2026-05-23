@extends('dblens::layout')

@section('content')
<div x-data="dbLensER({
        diagram: {{ Illuminate\Support\Js::from($diagram) }},
        fks: {{ Illuminate\Support\Js::from($fks) }},
        savedViews: {{ Illuminate\Support\Js::from($saved_views ?? []) }},
        saveUrl: @js(route('dblens.er.view.save', ['connection' => $connection])),
        deleteUrlTpl: @js(rtrim(route('dblens.er.view.delete', ['connection' => $connection, 'id' => '__ID__']), '/')),
        csrf: @js(csrf_token()),
     })"
     x-init="init()"
     class="flex flex-col" style="height: calc(100vh - 6rem);">

    {{-- ─── Toolbar ─────────────────────────────────────────────── --}}
    <div class="bg-white rounded shadow-sm border border-slate-200 mb-3 p-2 flex items-center gap-2 flex-wrap">
        <div class="flex-1 min-w-[200px] flex items-center gap-1">
            <input type="text" x-model="search"
                   @input="matchIndex = 0; centerOnMatch(0)"
                   @keydown.enter.prevent="centerOnMatch($event.shiftKey ? -1 : 1)"
                   @keydown.escape="search = ''"
                   placeholder="🔍 Find table… (Enter = next, Shift+Enter = prev)"
                   class="flex-1 px-3 py-1.5 border border-slate-300 rounded text-sm">
            <span x-show="search.trim() !== ''" class="text-xs text-slate-500 mono whitespace-nowrap"
                  x-text="matchCount() === 0 ? 'no matches' : (matchIndex + 1) + ' / ' + matchCount()"></span>
        </div>
        <button type="button" @click="zoom(0.8)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded text-sm" title="Zoom out">−</button>
        <span class="text-xs text-slate-500 mono w-12 text-center" x-text="Math.round(scale*100)+'%'"></span>
        <button type="button" @click="zoom(1.25)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded text-sm" title="Zoom in">+</button>
        <button type="button" @click="resetView()" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded text-sm" title="Reset view">⌖</button>
        <button type="button" @click="fitAll()" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded text-sm">Fit all</button>
        <button type="button" @click="autoLayout()" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded text-sm">Auto layout</button>
        <label class="flex items-center gap-1 text-xs ml-2"><input type="checkbox" x-model="onlyKeys"> compact (keys only)</label>
        <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="showArrows" @change="renderArrows()"> arrows</label>
        <label class="flex items-center gap-1 text-xs" title="Hide every table except the active (pinned) one"><input type="checkbox" x-model="showActiveOnly"> active only</label>
        <label class="flex items-center gap-1 text-xs" title="Show the active table and only tables it has FK relationships with"><input type="checkbox" x-model="showRelatedOnly"> related only</label>
        <span x-show="active" x-cloak class="text-xs px-2 py-0.5 bg-sky-100 text-sky-700 rounded mono">
            📌 <span x-text="active"></span>
            <button type="button" @click="active = null; renderArrows()" class="ml-1 text-sky-500 hover:text-sky-800">✕</button>
        </span>
        {{-- Saved views --}}
        <div class="flex items-center gap-1 ml-2 border-l border-slate-200 pl-2">
            <select x-model="selectedViewId" @change="loadSelectedView()"
                    class="px-2 py-1 border border-slate-300 rounded text-xs max-w-[160px]">
                <option value="">— saved views —</option>
                <template x-for="v in savedViews" :key="v.id">
                    <option :value="v.id" x-text="v.name"></option>
                </template>
            </select>
            <button type="button" @click="promptSaveView()" class="px-2 py-1 bg-emerald-600 text-white rounded text-xs hover:bg-emerald-700" title="Save current view">💾</button>
            <button type="button" x-show="selectedViewId" x-cloak
                    @click="deleteSelectedView()"
                    class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200" title="Delete saved view">🗑</button>
        </div>

        <span class="text-xs text-slate-500 ml-auto mono">
            {{ count($diagram) }} tables · {{ count($fks) }} FKs
        </span>
    </div>

    {{-- ─── Canvas + side panel ────────────────────────────────── --}}
    <div class="flex-1 min-h-0 flex gap-2">
    <div x-ref="viewport"
         class="flex-1 min-h-0 bg-slate-50 rounded border border-slate-200 relative overflow-hidden"
         @wheel.prevent="onWheel($event)"
         @mousedown="startPan($event)"
         @mousemove.window="onPan($event)"
         @mouseup.window="endPan()">

        <div class="absolute top-0 left-0 origin-top-left will-change-transform"
             :style="`transform: translate(${pan.x}px, ${pan.y}px) scale(${scale});`"
             style="width: 1px; height: 1px;">

            {{-- SVG arrows under the table cards (rendered imperatively to dodge
                 <template x-for> SVG-namespace issues) --}}
            <svg x-ref="svg"
                 :width="canvasW" :height="canvasH"
                 :style="`width: ${canvasW}px; height: ${canvasH}px;`"
                 class="absolute top-0 left-0"
                 xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <marker id="er-arrow"     viewBox="0 0 12 12" refX="10" refY="6" markerWidth="6" markerHeight="6" orient="auto-start-reverse" markerUnits="userSpaceOnUse">
                        <path d="M0,0 L12,6 L0,12 L3,6 Z" fill="#7c3aed"/>
                    </marker>
                    <marker id="er-arrow-hot" viewBox="0 0 12 12" refX="10" refY="6" markerWidth="8" markerHeight="8" orient="auto-start-reverse" markerUnits="userSpaceOnUse">
                        <path d="M0,0 L12,6 L0,12 L3,6 Z" fill="#0284c7"/>
                    </marker>
                </defs>
                <g x-ref="arrowsGroup"></g>
            </svg>

            {{-- Floating arrow info popup (positioned in canvas coords) --}}
            <div x-show="selectedArrow" x-cloak
                 class="absolute z-30 -translate-x-1/2 -translate-y-full bg-white border border-violet-400 rounded-lg shadow-xl px-3 py-2 text-xs mono whitespace-nowrap"
                 :style="selectedArrow ? `left: ${arrowMidpoint(selectedArrow).x}px; top: ${arrowMidpoint(selectedArrow).y - 6}px;` : ''">
                <div class="flex items-center gap-2">
                    <span class="text-violet-700" x-text="selectedArrow?.from + '.' + selectedArrow?.fromCol"></span>
                    <span class="text-slate-400">→</span>
                    <span class="text-violet-700" x-text="selectedArrow?.to + '.' + selectedArrow?.toCol"></span>
                    <button type="button" @click="selectedArrow = null" class="ml-1 text-slate-400 hover:text-slate-700">✕</button>
                </div>
                <div x-show="selectedArrow?.name" class="text-[10px] text-slate-400 mt-0.5" x-text="selectedArrow?.name"></div>
            </div>

            {{-- Table cards --}}
            <template x-for="t in tables" :key="t.name">
                <div x-show="isTableVisible(t)"
                     class="absolute border-2 rounded-lg shadow-sm text-xs select-none overflow-hidden"
                     :class="[
                        active === t.name ? 'bg-white border-sky-600 shadow-lg ring-2 ring-sky-200' :
                            (showRelatedOnly && active && (neighbors[active] || new Set()).has(t.name)
                                ? 'bg-white border-emerald-600 shadow-lg ring-2 ring-emerald-300'
                                : (highlighted === t.name ? 'bg-white border-sky-400 shadow-md' : 'bg-white border-slate-300')),
                        (matchesSearch(t) || (showRelatedOnly && active && (t.name === active || (neighbors[active] || new Set()).has(t.name)))) ? '' : 'opacity-30'
                     ]"
                     :style="`left: ${t.x}px; top: ${t.y}px; width: 260px;`"
                     @mousedown.stop="startDragTable($event, t)"
                     @mouseenter="highlighted = t.name"
                     @mouseleave="highlighted = null">

                    <div class="px-2 py-1 text-white rounded-t-md flex items-center justify-between cursor-move gap-1"
                         :class="active === t.name
                             ? 'bg-sky-700'
                             : (showRelatedOnly && active && (neighbors[active] || new Set()).has(t.name) ? 'bg-emerald-600' : 'bg-slate-800')">
                        <button type="button"
                                @click.stop="toggleActive(t.name)"
                                @mousedown.stop
                                @dblclick.stop
                                class="text-sm leading-none hover:scale-110 transition"
                                :class="active === t.name ? 'text-amber-400' : 'text-slate-500 hover:text-amber-300'"
                                :title="active === t.name ? 'Click to deactivate' : 'Click to activate'"
                                x-text="active === t.name ? '★' : '☆'"></button>
                        <span class="mono truncate flex-1" x-text="t.name"></span>
                        <span class="text-[10px] text-slate-400" x-text="`${t.columns.length}`"></span>
                        <a :href="`{{ url(config('dblens.viewer.path', 'dblens').'/'.$connection.'/t') }}/${t.name}`"
                           target="_blank" @click.stop @mousedown.stop
                           class="text-slate-400 hover:text-sky-300 ml-1" title="Open table">↗</a>
                    </div>

                    <div class="divide-y divide-slate-100">
                        <template x-for="(c, idx) in visibleColumns(t)" :key="c.name">
                            <div class="flex items-start gap-1 px-2 py-0.5">
                                <span class="shrink-0 mono"
                                      :class="c.is_pk ? 'font-bold text-amber-600' : (c.is_fk ? 'text-violet-600' : (c.is_referenced ? 'text-sky-600' : 'text-slate-700'))">
                                    <span x-show="c.is_pk" title="Primary key">🔑</span>
                                    <span x-show="c.is_fk" title="Foreign key">🔗</span>
                                    <span x-show="!c.is_pk && !c.is_fk && c.is_referenced" title="Referenced">🎯</span>
                                </span>
                                <span class="mono break-all flex-1 leading-tight"
                                      :class="c.is_pk ? 'font-bold text-amber-600' : (c.is_fk ? 'text-violet-600' : (c.is_referenced ? 'text-sky-600' : 'text-slate-700'))"
                                      x-text="c.name"></span>
                                <span class="shrink-0 text-slate-400 mono text-[10px] leading-tight pt-0.5"
                                      x-text="shortType(c.type)" :title="c.type"></span>
                            </div>
                        </template>
                        <template x-if="hiddenCount(t) > 0">
                            <div class="px-2 py-0.5 text-center text-slate-400 italic bg-slate-50 cursor-pointer hover:bg-slate-100"
                                 @click="toggleExpand(t.name)">
                                <template x-if="!expanded[t.name]">
                                    <span>· · ·   <span x-text="`${hiddenCount(t)} more columns`"></span></span>
                                </template>
                                <template x-if="expanded[t.name]">
                                    <span>↑ Collapse</span>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- Help footer --}}
        <div class="absolute bottom-2 right-2 bg-white/90 border border-slate-200 rounded px-3 py-1 text-[10px] text-slate-500 pointer-events-none">
            Scroll = zoom · Drag background = pan · Drag header = move table
        </div>
    </div>

    {{-- ─── Side panel: active table relationships ─────────────── --}}
    <aside x-show="active && showSidePanel" x-cloak
           class="w-80 bg-white border border-slate-200 rounded shadow-sm overflow-auto scroll-thin">
        <template x-if="active">
        <div>
            <div class="px-3 py-2 border-b bg-slate-50 flex items-center justify-between sticky top-0 z-10">
                <div class="min-w-0">
                    <div class="text-[10px] text-slate-400 uppercase">Relationships</div>
                    <div class="font-semibold mono truncate" x-text="active"></div>
                </div>
                <button type="button" @click="showSidePanel = false" class="text-slate-400 hover:text-slate-700 text-sm">✕</button>
            </div>

            <div class="px-3 py-2 text-xs">
                <div class="text-[10px] uppercase text-slate-400 mb-1 flex justify-between">
                    <span>Outgoing FKs <span class="text-slate-500" x-text="`(${outgoingFks(active).length})`"></span></span>
                </div>
                <template x-if="outgoingFks(active).length === 0">
                    <div class="text-slate-400 italic mb-2">No outgoing references.</div>
                </template>
                <template x-for="fk in outgoingFks(active)" :key="fk.name">
                    <div class="border-l-2 border-violet-300 pl-2 mb-2 hover:bg-slate-50 cursor-pointer"
                         @click="active = fk.foreign_table">
                        <div class="mono text-violet-700" x-text="`${fk.column} → ${fk.foreign_table}.${fk.foreign_column}`"></div>
                        <div class="text-[10px] text-slate-400 mono" x-text="fk.name"></div>
                    </div>
                </template>
            </div>

            <div class="px-3 py-2 border-t text-xs">
                <div class="text-[10px] uppercase text-slate-400 mb-1 flex justify-between">
                    <span>Incoming FKs <span class="text-slate-500" x-text="`(${incomingFks(active).length})`"></span></span>
                </div>
                <template x-if="incomingFks(active).length === 0">
                    <div class="text-slate-400 italic mb-2">Nothing references this table.</div>
                </template>
                <template x-for="fk in incomingFks(active)" :key="fk.name">
                    <div class="border-l-2 border-emerald-300 pl-2 mb-2 hover:bg-slate-50 cursor-pointer"
                         @click="active = fk.table">
                        <div class="mono text-emerald-700" x-text="`${fk.table}.${fk.column} → ${fk.foreign_column}`"></div>
                        <div class="text-[10px] text-slate-400 mono" x-text="fk.name"></div>
                    </div>
                </template>
            </div>
        </div>
        </template>
    </aside>

    {{-- Floating toggle when panel is hidden --}}
    <button type="button" x-show="active && ! showSidePanel" x-cloak
            @click="showSidePanel = true"
            class="fixed right-4 top-1/2 -translate-y-1/2 bg-sky-600 text-white px-2 py-3 rounded-l shadow-lg z-20 text-xs">
        ◀ relations
    </button>
    </div>
</div>

<script>
function dbLensER(cfg) {
    return {
        rawTables: cfg.diagram,
        rawFks: cfg.fks,
        tables: [],            // [{name, columns, x, y}]
        arrows: [],            // [{d, from, to}]
        pan: { x: 0, y: 0 },
        scale: 1,
        panning: false,
        panStart: null,
        draggingTable: null,
        dragOffset: { x: 0, y: 0 },
        canvasW: 4000,
        canvasH: 3000,
        expanded: {},
        onlyKeys: true,
        showArrows: true,
        showActiveOnly: true,
        showRelatedOnly: true,
        search: '',
        matchIndex: 0,
        highlighted: null,
        active: null,
        selectedArrow: null,
        showSidePanel: true,
        neighbors: {},   // {tableName: Set(otherTableName)}
        savedViews: cfg.savedViews || [],
        selectedViewId: '',
        saveUrl: cfg.saveUrl,
        deleteUrlTpl: cfg.deleteUrlTpl,
        csrf: cfg.csrf,

        init() {
            this.buildNeighbors();
            this.autoLayout();
            this.$watch('onlyKeys', () => this.$nextTick(() => this.computeArrows()));
            this.$watch('expanded', () => this.$nextTick(() => this.computeArrows()));
            this.$watch('active', () => {
                this.renderArrows();
                if (this.showRelatedOnly && this.active) {
                    this.layoutRelated();
                    this.$nextTick(() => { this.computeArrows(); this.fitVisible(); });
                }
            });
            this.$watch('showActiveOnly', () => this.renderArrows());
            this.$watch('showRelatedOnly', (v) => {
                if (v && this.active) this.layoutRelated();
                this.$nextTick(() => {
                    this.computeArrows();
                    if (v && this.active) this.fitVisible();
                });
            });
            this.fitAll();
        },

        buildNeighbors() {
            const n = {};
            for (const fk of this.rawFks) {
                (n[fk.table] ??= new Set()).add(fk.foreign_table);
                (n[fk.foreign_table] ??= new Set()).add(fk.table);
            }
            this.neighbors = n;
        },

        toggleActive(name) {
            this.active = (this.active === name) ? null : name;
        },

        captureState() {
            return {
                active: this.active,
                onlyKeys: this.onlyKeys,
                showArrows: this.showArrows,
                showActiveOnly: this.showActiveOnly,
                showRelatedOnly: this.showRelatedOnly,
                showSidePanel: this.showSidePanel,
                pan: { ...this.pan },
                scale: this.scale,
                expanded: { ...this.expanded },
                positions: Object.fromEntries(this.tables.map(t => [t.name, { x: t.x, y: t.y }])),
            };
        },

        applyState(s) {
            if (! s) return;
            if (s.positions) {
                for (const t of this.tables) {
                    if (s.positions[t.name]) {
                        t.x = s.positions[t.name].x;
                        t.y = s.positions[t.name].y;
                    }
                }
            }
            this.onlyKeys = !! s.onlyKeys;
            this.showArrows = !! s.showArrows;
            this.showActiveOnly = !! s.showActiveOnly;
            this.showRelatedOnly = !! s.showRelatedOnly;
            this.showSidePanel = s.showSidePanel !== false;
            this.expanded = s.expanded || {};
            this.active = s.active || null;
            if (s.pan) this.pan = { ...s.pan };
            if (typeof s.scale === 'number') this.scale = s.scale;
            this.$nextTick(() => this.computeArrows());
        },

        async promptSaveView() {
            const existing = this.savedViews.find(v => v.id === this.selectedViewId);
            const defaultName = existing?.name || (this.active ? `${this.active} view` : 'view');
            const name = window.prompt('Name for this view:', defaultName);
            if (! name) return;
            const body = {
                id: existing?.id || null,
                name,
                state: this.captureState(),
            };
            const res = await fetch(this.saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                body: JSON.stringify(body),
            });
            if (! res.ok) { alert('Save failed.'); return; }
            const data = await res.json();
            const i = this.savedViews.findIndex(v => v.id === data.view.id);
            if (i >= 0) this.savedViews[i] = data.view;
            else this.savedViews.push(data.view);
            this.selectedViewId = data.view.id;
        },

        loadSelectedView() {
            const v = this.savedViews.find(x => x.id === this.selectedViewId);
            if (v) this.applyState(v.state);
        },

        async deleteSelectedView() {
            const v = this.savedViews.find(x => x.id === this.selectedViewId);
            if (! v) return;
            if (! window.confirm(`Delete saved view "${v.name}"?`)) return;
            const url = this.deleteUrlTpl.replace('__ID__', encodeURIComponent(v.id));
            const res = await fetch(url, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
            });
            if (! res.ok) { alert('Delete failed.'); return; }
            this.savedViews = this.savedViews.filter(x => x.id !== v.id);
            this.selectedViewId = '';
        },

        outgoingFks(name) {
            return this.rawFks.filter(f => f.table === name);
        },
        incomingFks(name) {
            return this.rawFks.filter(f => f.foreign_table === name);
        },

        isTableVisible(t) {
            // `active only` controls arrow scope, not table visibility.
            if (this.showRelatedOnly && this.active) {
                if (t.name === this.active) return true;
                return (this.neighbors[this.active] || new Set()).has(t.name);
            }
            return true;
        },

        matchesSearch(t) {
            const q = (this.search || '').trim().toLowerCase();
            return q === '' || t.name.toLowerCase().includes(q);
        },

        matches() {
            const q = (this.search || '').trim().toLowerCase();
            if (q === '') return [];
            return this.tables.filter(x => x.name.toLowerCase().includes(q));
        },
        matchCount() { return this.matches().length; },

        centerOnMatch(step) {
            const m = this.matches();
            if (m.length === 0) { this.highlighted = null; return; }
            if (step === 0) {
                this.matchIndex = 0;
            } else {
                this.matchIndex = ((this.matchIndex + step) % m.length + m.length) % m.length;
            }
            const t = m[this.matchIndex];
            this.highlighted = t.name;
            const viewport = this.$refs.viewport;
            if (! viewport) return;
            const w = viewport.clientWidth, h = viewport.clientHeight;
            // Keep current scale; just pan so the table card centers in the viewport.
            this.pan.x = w/2 - (t.x + 130) * this.scale;
            this.pan.y = h/2 - (t.y + 80) * this.scale;
        },

        autoLayout() {
            // Simple grid layout, sized by column count
            const cols = Math.ceil(Math.sqrt(this.rawTables.length));
            const cellW = 280, cellH = 360;
            let i = 0;
            this.tables = this.rawTables.map(t => {
                const x = (i % cols) * cellW + 40;
                const y = Math.floor(i / cols) * cellH + 40;
                i++;
                return { ...t, x, y };
            });
            this.canvasW = Math.max(2000, cols * cellW + 100);
            this.canvasH = Math.max(2000, Math.ceil(this.rawTables.length / cols) * cellH + 100);
            this.$nextTick(() => this.computeArrows());
        },

        resetView() { this.pan = {x:0,y:0}; this.scale = 1; },

        fitAll() { this.fitTables(this.tables); },

        layoutRelated() {
            if (! this.active) return;
            const center = this.tables.find(t => t.name === this.active);
            if (! center) return;
            const neighborNames = [...(this.neighbors[this.active] || new Set())];
            const neighbors = neighborNames
                .map(n => this.tables.find(t => t.name === n))
                .filter(Boolean);
            if (neighbors.length === 0) return;

            const CARD_W = 260, CARD_H = 360;
            // Anchor the active card; arrange neighbors on a ring around it.
            // Radius grows with neighbor count so cards don't overlap.
            const cx = center.x + CARD_W / 2;
            const cy = center.y + CARD_H / 2;
            const r = Math.max(CARD_W * 1.2, (CARD_W + 40) * neighbors.length / (2 * Math.PI));
            neighbors.forEach((n, i) => {
                const angle = (i / neighbors.length) * 2 * Math.PI - Math.PI / 2;
                n.x = cx + r * Math.cos(angle) - CARD_W / 2;
                n.y = cy + r * Math.sin(angle) - CARD_H / 2;
            });
        },

        fitVisible() {
            const visible = this.tables.filter(t => this.isTableVisible(t));
            this.fitTables(visible.length ? visible : this.tables);
        },

        fitTables(list) {
            if (list.length === 0) return;
            const CARD_W = 260, CARD_H = 360;
            const minX = Math.min(...list.map(t => t.x));
            const minY = Math.min(...list.map(t => t.y));
            const maxX = Math.max(...list.map(t => t.x + CARD_W));
            const maxY = Math.max(...list.map(t => t.y + CARD_H));
            const viewport = this.$refs.viewport;
            const w = viewport?.clientWidth || 800;
            const h = viewport?.clientHeight || 600;
            const sx = w / (maxX - minX + 80);
            const sy = h / (maxY - minY + 80);
            // Cap min scale so cards stay readable even with many neighbors.
            this.scale = Math.max(0.55, Math.min(1.2, Math.min(sx, sy)));
            this.pan.x = (w - (maxX - minX) * this.scale) / 2 - minX * this.scale;
            this.pan.y = (h - (maxY - minY) * this.scale) / 2 - minY * this.scale;
        },

        zoom(factor) {
            this.scale = Math.max(0.1, Math.min(3, this.scale * factor));
        },

        onWheel(e) {
            const factor = e.deltaY < 0 ? 1.1 : 0.9;
            // zoom toward cursor
            const rect = e.currentTarget.getBoundingClientRect();
            const mx = e.clientX - rect.left;
            const my = e.clientY - rect.top;
            const before = this.scale;
            this.scale = Math.max(0.1, Math.min(3, this.scale * factor));
            const ratio = this.scale / before;
            this.pan.x = mx - (mx - this.pan.x) * ratio;
            this.pan.y = my - (my - this.pan.y) * ratio;
        },

        startPan(e) {
            if (e.target.closest('.cursor-move')) return; // table drag handles its own
            this.panning = true;
            this.panStart = { x: e.clientX - this.pan.x, y: e.clientY - this.pan.y };
        },
        viewportRect() {
            return this.$refs.viewport?.getBoundingClientRect() || { left: 0, top: 0 };
        },
        onPan(e) {
            if (this.draggingTable) {
                const t = this.draggingTable;
                const r = this.viewportRect();
                const localX = e.clientX - r.left;
                const localY = e.clientY - r.top;
                // Cursor's offset INSIDE the card was captured at start in
                // *screen pixels*; convert to canvas units by dividing by scale.
                t.x = (localX - this.pan.x) / this.scale - this.dragOffset.x / this.scale;
                t.y = (localY - this.pan.y) / this.scale - this.dragOffset.y / this.scale;
                this.computeArrows();
                return;
            }
            if (! this.panning) return;
            this.pan.x = e.clientX - this.panStart.x;
            this.pan.y = e.clientY - this.panStart.y;
        },
        endPan() {
            this.panning = false;
            this.draggingTable = null;
        },

        startDragTable(e, t) {
            this.draggingTable = t;
            const rect = e.currentTarget.getBoundingClientRect();
            this.dragOffset.x = e.clientX - rect.left;
            this.dragOffset.y = e.clientY - rect.top;
        },

        shortType(t) {
            return String(t).replace(/\(.*\)/, '').toLowerCase();
        },
        isVisibleColumn(c) {
            if (! this.onlyKeys) return true;
            return c.is_key;
        },
        visibleColumns(t) {
            if (this.expanded[t.name]) return t.columns;
            return t.columns.filter(c => this.isVisibleColumn(c));
        },
        hiddenCount(t) {
            return t.columns.length - this.visibleColumns(t).length + (this.expanded[t.name] ? 0 : 0);
        },
        toggleExpand(name) {
            this.expanded = { ...this.expanded, [name]: ! this.expanded[name] };
        },

        computeArrows() {
            const CARD_W = 260;
            const tableMap = Object.fromEntries(this.tables.map(t => [t.name, t]));
            // First pass: gather raw endpoints + group by unordered table-pair so
            // overlapping arrows between the same two tables can be fanned out.
            const raw = [];
            const pairGroups = {};
            for (const fk of this.rawFks) {
                const from = tableMap[fk.table];
                const to = tableMap[fk.foreign_table];
                if (! from || ! to || from === to) continue;
                const fromY = from.y + 30 + this.columnYOffset(from, fk.column);
                const toY   = to.y   + 30 + this.columnYOffset(to,   fk.foreign_column);
                let sx, ex;
                if (to.x + CARD_W/2 > from.x + CARD_W/2) { sx = from.x + CARD_W; ex = to.x; }
                else                                      { sx = from.x;          ex = to.x + CARD_W; }
                const key = [fk.table, fk.foreign_table].sort().join('||');
                const item = { fk, sx, ex, sy: fromY, ey: toY };
                (pairGroups[key] ??= []).push(item);
                raw.push({ key, item });
            }

            // Distribute duplicates: shift each arrow's endpoints + bend so they
            // don't paint over each other when columns collapse to the same row.
            const SPREAD_Y = 22;       // vertical separation between siblings
            const BEND_STEP = 60;      // extra control-point bend per sibling
            const indexInGroup = {};
            const arrows = [];
            for (const { key, item } of raw) {
                const group = pairGroups[key];
                indexInGroup[key] = (indexInGroup[key] ?? -1) + 1;
                const i = indexInGroup[key];
                const n = group.length;
                const slot = n > 1 ? (i - (n - 1) / 2) : 0;
                const sy = item.sy + slot * SPREAD_Y;
                const ey = item.ey + slot * SPREAD_Y;
                const sx = item.sx, ex = item.ex;
                const dx = Math.abs(ex - sx);
                // Each sibling gets a distinct bend so curves are visually parallel.
                const cx = Math.max(40, dx / 2) + Math.abs(slot) * BEND_STEP;
                const d = `M ${sx} ${sy} C ${sx + (ex > sx ? cx : -cx)} ${sy}, ${ex - (ex > sx ? cx : -cx)} ${ey}, ${ex} ${ey}`;
                arrows.push({ d, sx, sy, ex, ey, from: item.fk.table, to: item.fk.foreign_table, fromCol: item.fk.column, toCol: item.fk.foreign_column, name: item.fk.name });
            }
            this.arrows = arrows;
            this.renderArrows();
        },

        renderArrows() {
            const g = this.$refs.arrowsGroup;
            if (! g) return;
            const SVG = 'http://www.w3.org/2000/svg';
            while (g.firstChild) g.removeChild(g.firstChild);
            if (! this.showArrows) return;
            // "active only" scopes arrows to the active table; with no active, hide all.
            if (this.showActiveOnly && ! this.active) return;
            const focus = this.active;
            for (const a of this.arrows) {
                if (this.showActiveOnly && focus && a.from !== focus && a.to !== focus) continue;
                const tableHidden = this.tables.find(t => t.name === a.from && ! this.isTableVisible(t))
                                 || this.tables.find(t => t.name === a.to   && ! this.isTableVisible(t));
                if (tableHidden) continue;
                const hot = !! focus;
                const dim = false;
                const grp = document.createElementNS(SVG, 'g');
                grp.setAttribute('opacity', dim ? '0.08' : (hot ? '1' : '0.55'));

                const circle = document.createElementNS(SVG, 'circle');
                circle.setAttribute('cx', a.sx);
                circle.setAttribute('cy', a.sy);
                circle.setAttribute('r', hot ? 5 : 3.5);
                circle.setAttribute('fill', hot ? '#0284c7' : '#7c3aed');
                grp.appendChild(circle);

                const path = document.createElementNS(SVG, 'path');
                path.setAttribute('d', a.d);
                path.setAttribute('stroke', hot ? '#0284c7' : '#7c3aed');
                path.setAttribute('stroke-width', hot ? 2.5 : 1.5);
                path.setAttribute('fill', 'none');
                path.setAttribute('marker-end', hot ? 'url(#er-arrow-hot)' : 'url(#er-arrow)');
                path.style.cursor = 'pointer';
                path.style.pointerEvents = 'stroke';
                // Invisible wider hit area for easier clicking.
                const hit = document.createElementNS(SVG, 'path');
                hit.setAttribute('d', a.d);
                hit.setAttribute('stroke', 'transparent');
                hit.setAttribute('stroke-width', 14);
                hit.setAttribute('fill', 'none');
                hit.style.cursor = 'pointer';
                hit.style.pointerEvents = 'stroke';
                const handler = (e) => {
                    e.stopPropagation();
                    this.selectedArrow = a;
                };
                path.addEventListener('click', handler);
                hit.addEventListener('click', handler);
                grp.appendChild(hit);
                grp.appendChild(path);

                g.appendChild(grp);
            }
        },

        arrowMidpoint(a) {
            // Cubic bezier B(0.5) for the same control points used in computeArrows.
            const dx = Math.abs(a.ex - a.sx);
            const cx = Math.max(40, dx / 2);
            const cp1x = a.sx + (a.ex > a.sx ? cx : -cx);
            const cp2x = a.ex - (a.ex > a.sx ? cx : -cx);
            // B(0.5) = (P0 + 3*P1 + 3*P2 + P3) / 8
            return {
                x: (a.sx + 3*cp1x + 3*cp2x + a.ex) / 8,
                y: (a.sy + 3*a.sy + 3*a.ey + a.ey) / 8,
            };
        },

        columnYOffset(t, colName) {
            const visible = this.visibleColumns(t);
            const idx = visible.findIndex(c => c.name === colName);
            if (idx === -1) return 8; // not visible (e.g. compact mode hides it) → header
            return idx * 18 + 8;
        },
    }
}
</script>
@endsection
