/**
 * Result Slip Canvas Editor
 * A true A4-proportioned, free-position drag/resize/style canvas used by both
 * the platform base-template builder and the school custom-template builder.
 *
 * Layout data shape (stored in the `layout_json` column as-is):
 * {
 *   "page": { "background_image": "uploads/.../bg.jpg"|null, "background_color": "#ffffff" },
 *   "elements": [
 *     { "id": "el_xxx", "key": "student_name", "label": "Student Name",
 *       "x": 20, "y": 15, "w": 60, "h": 10,      // all in mm, from top-left of A4 page
 *       "z": 1,
 *       "fontFamily": "Georgia, serif", "fontSize": 11, "color": "#111111",
 *       "bold": false, "italic": false, "align": "left" }
 *   ]
 * }
 *
 * A4 = 210mm x 297mm. Editing canvas renders at MM_TO_PX px/mm.
 */
(function (global) {
    const A4_W_MM = 210;
    const A4_H_MM = 297;
    const MM_TO_PX = 3.2; // editing zoom factor only — saved data stays in mm

    const DEFAULT_SIZE = {
        school_logo: { w: 28, h: 28 }, student_photo: { w: 26, h: 32 },
        subjects_table: { w: 170, h: 60 }, signature_line: { w: 45, h: 14 },
        school_name: { w: 170, h: 16 },
    };
    const FALLBACK_SIZE = { w: 60, h: 10 };

    const FONT_CHOICES = [
        'Georgia, serif', 'Arial, sans-serif', 'Helvetica, sans-serif',
        '"Times New Roman", serif', '"Courier New", monospace', 'Verdana, sans-serif',
        '"Trebuchet MS", sans-serif',
    ];

    function uidLocal() {
        return 'el_' + Math.random().toString(36).slice(2, 10);
    }

    function normalizeLayout(raw) {
        let data;
        try { data = typeof raw === 'string' ? JSON.parse(raw) : raw; } catch (e) { data = null; }
        if (!data || Array.isArray(data)) {
            // Legacy format: a plain ordered array of block keys. Convert to a
            // simple stacked layout so old templates still open in the editor.
            const keys = Array.isArray(data) ? data : [];
            let y = 10;
            const elements = keys.map((k) => {
                const size = DEFAULT_SIZE[k] || FALLBACK_SIZE;
                const el = {
                    id: uidLocal(), key: (typeof k === 'string') ? k : k.key, label: (typeof k === 'object' && k.label) ? k.label : k,
                    x: 20, y, w: size.w, h: size.h, z: 1,
                    fontFamily: 'Georgia, serif', fontSize: 11, color: '#111111',
                    bold: false, italic: false, align: 'left',
                };
                y += size.h + 4;
                return el;
            });
            return { page: { background_image: null, background_color: '#ffffff' }, elements };
        }
        return {
            page: Object.assign({ background_image: null, background_color: '#ffffff' }, data.page || {}),
            elements: Array.isArray(data.elements) ? data.elements : [],
        };
    }

    function createCanvasEditor(opts) {
        // opts: { availableBlocksEl, canvasWrapEl, inspectorEl, jsonField,
        //         bgFileInput, bgRemoveBtn, blockLabels, sampleHtml, initialLayout,
        //         uploadedBgUrl (existing bg image URL to show, or null) }
        const state = normalizeLayout(opts.initialLayout);
        if (opts.uploadedBgUrl) state.page.background_image_url = opts.uploadedBgUrl;
        let selectedId = null;
        let bgCleared = false;

        const wrap = opts.canvasWrapEl;
        wrap.innerHTML = '';
        wrap.style.position = 'relative';
        wrap.style.width = (A4_W_MM * MM_TO_PX) + 'px';
        wrap.style.height = (A4_H_MM * MM_TO_PX) + 'px';
        wrap.style.background = state.page.background_color || '#ffffff';
        wrap.style.backgroundSize = 'cover';
        wrap.style.backgroundPosition = 'center';
        wrap.style.boxShadow = '0 0 0 1px rgba(0,0,0,0.08), 0 8px 24px rgba(0,0,0,0.12)';
        wrap.style.margin = '0 auto';
        wrap.style.overflow = 'hidden';
        wrap.classList.add('rst-canvas');

        function applyBackground() {
            if (state.page.background_image_url && !bgCleared) {
                wrap.style.backgroundImage = 'url(' + state.page.background_image_url + ')';
            } else {
                wrap.style.backgroundImage = 'none';
            }
        }
        applyBackground();

        function mmToPx(mm) { return mm * MM_TO_PX; }
        function pxToMm(px) { return px / MM_TO_PX; }

        function serialize() {
            const out = {
                page: {
                    background_image: bgCleared ? null : (state.page.background_image || null),
                    background_color: state.page.background_color || '#ffffff',
                    clear_background: bgCleared,
                },
                elements: state.elements.map((e) => ({
                    id: e.id, key: e.key, label: e.label,
                    x: round2(e.x), y: round2(e.y), w: round2(e.w), h: round2(e.h), z: e.z || 1,
                    fontFamily: e.fontFamily, fontSize: e.fontSize, color: e.color,
                    bold: !!e.bold, italic: !!e.italic, align: e.align || 'left',
                })),
            };
            opts.jsonField.value = JSON.stringify(out);
        }
        function round2(n) { return Math.round(n * 100) / 100; }

        function elStyle(e) {
            return [
                'position:absolute',
                'left:' + mmToPx(e.x) + 'px', 'top:' + mmToPx(e.y) + 'px',
                'width:' + mmToPx(e.w) + 'px', 'height:' + mmToPx(e.h) + 'px',
                'z-index:' + (e.z || 1),
                'font-family:' + (e.fontFamily || 'Georgia, serif'),
                'font-size:' + (e.fontSize || 11) + 'px',
                'color:' + (e.color || '#111111'),
                'font-weight:' + (e.bold ? 'bold' : 'normal'),
                'font-style:' + (e.italic ? 'italic' : 'normal'),
                'text-align:' + (e.align || 'left'),
                'overflow:hidden', 'box-sizing:border-box', 'padding:2px', 'cursor:move',
            ].join(';');
        }

        function renderCanvas() {
            wrap.querySelectorAll('.rst-el').forEach((n) => n.remove());
            state.elements.sort((a, b) => (a.z || 1) - (b.z || 1)).forEach((e) => {
                const node = document.createElement('div');
                node.className = 'rst-el' + (e.id === selectedId ? ' rst-el-selected' : '');
                node.dataset.id = e.id;
                node.setAttribute('style', elStyle(e) + (e.id === selectedId ? ';outline:2px solid #6366f1;outline-offset:1px' : ';outline:1px dashed rgba(99,102,241,0.35)'));
                node.innerHTML = '<div style="pointer-events:none;width:100%;height:100%;overflow:hidden;">' + (opts.sampleHtml[e.key] || ('[' + e.label + ']')) + '</div>';

                node.addEventListener('mousedown', (ev) => startDrag(ev, e));
                node.addEventListener('click', (ev) => { ev.stopPropagation(); selectElement(e.id); });

                if (e.id === selectedId) {
                    const handle = document.createElement('div');
                    handle.className = 'rst-resize-handle';
                    handle.style.cssText = 'position:absolute;right:-5px;bottom:-5px;width:12px;height:12px;background:#6366f1;border:2px solid #fff;border-radius:3px;cursor:nwse-resize;z-index:9999;';
                    handle.addEventListener('mousedown', (ev) => startResize(ev, e));
                    node.appendChild(handle);

                    const del = document.createElement('button');
                    del.type = 'button';
                    del.textContent = '✕';
                    del.title = 'Remove from layout';
                    del.style.cssText = 'position:absolute;top:-10px;right:-10px;width:18px;height:18px;line-height:14px;background:#e11d48;color:#fff;border-radius:9999px;font-size:10px;font-weight:bold;border:2px solid #fff;cursor:pointer;z-index:9999;';
                    del.addEventListener('mousedown', (ev) => ev.stopPropagation());
                    del.addEventListener('click', (ev) => { ev.stopPropagation(); removeElement(e.id); });
                    node.appendChild(del);
                }
                wrap.appendChild(node);
            });
            renderPalette();
            renderInspector();
            serialize();
        }

        function startDrag(ev, e) {
            if (ev.target.classList.contains('rst-resize-handle')) return;
            ev.preventDefault();
            selectElement(e.id);
            const startX = ev.clientX, startY = ev.clientY;
            const origX = e.x, origY = e.y;
            function onMove(mv) {
                const dxMm = pxToMm(mv.clientX - startX);
                const dyMm = pxToMm(mv.clientY - startY);
                e.x = clamp(origX + dxMm, 0, A4_W_MM - e.w);
                e.y = clamp(origY + dyMm, 0, A4_H_MM - e.h);
                renderCanvas();
            }
            function onUp() {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            }
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        }

        function startResize(ev, e) {
            ev.preventDefault();
            ev.stopPropagation();
            const startX = ev.clientX, startY = ev.clientY;
            const origW = e.w, origH = e.h;
            function onMove(mv) {
                const dwMm = pxToMm(mv.clientX - startX);
                const dhMm = pxToMm(mv.clientY - startY);
                e.w = clamp(origW + dwMm, 8, A4_W_MM - e.x);
                e.h = clamp(origH + dhMm, 6, A4_H_MM - e.y);
                renderCanvas();
            }
            function onUp() {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            }
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        }

        function clamp(v, min, max) { if (max < min) max = min; return Math.max(min, Math.min(max, v)); }

        wrap.addEventListener('dragover', (ev) => ev.preventDefault());
        wrap.addEventListener('drop', (ev) => {
            ev.preventDefault();
            let data;
            try { data = JSON.parse(ev.dataTransfer.getData('text/plain')); } catch (e) { return; }
            if (!data || !data.key) return;
            const rect = wrap.getBoundingClientRect();
            const size = DEFAULT_SIZE[data.key] || FALLBACK_SIZE;
            const x = clamp(pxToMm(ev.clientX - rect.left) - size.w / 2, 0, A4_W_MM - size.w);
            const y = clamp(pxToMm(ev.clientY - rect.top) - size.h / 2, 0, A4_H_MM - size.h);
            const el = {
                id: uidLocal(), key: data.key, label: data.label,
                x, y, w: size.w, h: size.h, z: state.elements.length + 1,
                fontFamily: 'Georgia, serif', fontSize: 11, color: '#111111',
                bold: false, italic: false, align: 'left',
            };
            state.elements.push(el);
            selectElement(el.id);
        });
        wrap.addEventListener('click', () => selectElement(null));

        function selectElement(id) { selectedId = id; renderCanvas(); }
        function removeElement(id) {
            state.elements = state.elements.filter((e) => e.id !== id);
            if (selectedId === id) selectedId = null;
            renderCanvas();
        }

        function renderPalette() {
            const used = new Set(state.elements.map((e) => e.key));
            opts.availableBlocksEl.querySelectorAll('.rst-block').forEach((b) => {
                b.style.opacity = used.has(b.dataset.key) ? '0.4' : '1';
            });
        }

        function renderInspector() {
            const box = opts.inspectorEl;
            const e = state.elements.find((x) => x.id === selectedId);
            if (!e) {
                box.innerHTML = '<p class="text-xs italic text-[var(--text-secondary)]">Click a field on the canvas to edit its style, size, and position. Drag its body to move it, drag the bottom-right handle to resize.</p>';
                return;
            }
            box.innerHTML = '';
            const row = (labelText, inputHtml) => {
                const d = document.createElement('div');
                d.className = 'mb-2';
                d.innerHTML = '<label class="block text-[10px] font-bold uppercase text-[var(--text-secondary)] mb-1">' + labelText + '</label>' + inputHtml;
                return d;
            };
            const title = document.createElement('div');
            title.className = 'text-xs font-bold text-[var(--text-primary)] mb-3';
            title.textContent = e.label;
            box.appendChild(title);

            box.appendChild(row('Font', '<select data-f="fontFamily" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs">' +
                FONT_CHOICES.map((f) => '<option value="' + f.replace(/"/g, '&quot;') + '"' + (e.fontFamily === f ? ' selected' : '') + '>' + f.split(',')[0].replace(/"/g, '') + '</option>').join('') + '</select>'));

            const g1 = document.createElement('div');
            g1.className = 'grid grid-cols-2 gap-2 mb-2';
            g1.innerHTML =
                '<div><label class="block text-[10px] font-bold uppercase text-[var(--text-secondary)] mb-1">Size (pt)</label><input data-f="fontSize" type="number" min="6" max="48" value="' + e.fontSize + '" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs"></div>' +
                '<div><label class="block text-[10px] font-bold uppercase text-[var(--text-secondary)] mb-1">Color</label><input data-f="color" type="color" value="' + e.color + '" class="w-full h-[26px] bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg"></div>';
            box.appendChild(g1);

            const g2 = document.createElement('div');
            g2.className = 'flex items-center gap-3 mb-2';
            g2.innerHTML =
                '<label class="flex items-center gap-1 text-[10px] text-[var(--text-secondary)]"><input data-f="bold" type="checkbox" ' + (e.bold ? 'checked' : '') + '> Bold</label>' +
                '<label class="flex items-center gap-1 text-[10px] text-[var(--text-secondary)]"><input data-f="italic" type="checkbox" ' + (e.italic ? 'checked' : '') + '> Italic</label>';
            box.appendChild(g2);

            box.appendChild(row('Align', '<select data-f="align" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs">' +
                ['left', 'center', 'right'].map((a) => '<option value="' + a + '"' + (e.align === a ? ' selected' : '') + '>' + a + '</option>').join('') + '</select>'));

            const g3 = document.createElement('div');
            g3.className = 'grid grid-cols-2 gap-2 mb-2';
            g3.innerHTML =
                '<div><label class="block text-[10px] font-bold uppercase text-[var(--text-secondary)] mb-1">Width (mm)</label><input data-f="w" type="number" min="8" max="210" step="1" value="' + round2(e.w) + '" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs"></div>' +
                '<div><label class="block text-[10px] font-bold uppercase text-[var(--text-secondary)] mb-1">Height (mm)</label><input data-f="h" type="number" min="6" max="297" step="1" value="' + round2(e.h) + '" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs"></div>';
            box.appendChild(g3);

            const g4 = document.createElement('div');
            g4.className = 'grid grid-cols-2 gap-2 mb-3';
            g4.innerHTML =
                '<div><label class="block text-[10px] font-bold uppercase text-[var(--text-secondary)] mb-1">X (mm)</label><input data-f="x" type="number" min="0" max="210" step="1" value="' + round2(e.x) + '" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs"></div>' +
                '<div><label class="block text-[10px] font-bold uppercase text-[var(--text-secondary)] mb-1">Y (mm)</label><input data-f="y" type="number" min="0" max="297" step="1" value="' + round2(e.y) + '" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs"></div>';
            box.appendChild(g4);

            const zRow = document.createElement('div');
            zRow.className = 'flex gap-2 mb-3';
            zRow.innerHTML =
                '<button type="button" data-act="front" class="flex-1 px-2 py-1 text-[10px] font-bold rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)]">Bring to Front</button>' +
                '<button type="button" data-act="back" class="flex-1 px-2 py-1 text-[10px] font-bold rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)]">Send to Back</button>';
            box.appendChild(zRow);

            box.querySelectorAll('[data-f]').forEach((input) => {
                input.addEventListener('input', () => {
                    const f = input.dataset.f;
                    let v = input.type === 'checkbox' ? input.checked : input.value;
                    if (['fontSize', 'w', 'h', 'x', 'y'].includes(f)) v = parseFloat(v) || 0;
                    e[f] = v;
                    renderCanvas();
                });
            });
            box.querySelectorAll('[data-act]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const maxZ = Math.max(1, ...state.elements.map((x) => x.z || 1));
                    const minZ = Math.min(1, ...state.elements.map((x) => x.z || 1));
                    e.z = btn.dataset.act === 'front' ? maxZ + 1 : minZ - 1;
                    renderCanvas();
                });
            });
        }

        opts.availableBlocksEl.querySelectorAll('.rst-block').forEach((block) => {
            block.addEventListener('dragstart', (ev) => {
                ev.dataTransfer.setData('text/plain', JSON.stringify({ key: block.dataset.key, label: block.dataset.label }));
            });
        });

        if (opts.bgFileInput) {
            opts.bgFileInput.addEventListener('change', () => {
                const f = opts.bgFileInput.files[0];
                if (!f) return;
                bgCleared = false;
                const reader = new FileReader();
                reader.onload = () => {
                    state.page.background_image_url = reader.result; // preview only; real path set server-side on save
                    applyBackground();
                };
                reader.readAsDataURL(f);
            });
        }
        if (opts.bgRemoveBtn) {
            opts.bgRemoveBtn.addEventListener('click', () => {
                bgCleared = true;
                state.page.background_image_url = null;
                if (opts.bgFileInput) opts.bgFileInput.value = '';
                applyBackground();
                serialize();
            });
        }

        renderCanvas();
        return { serialize, getState: () => state };
    }

    global.ResultSlipCanvas = { createCanvasEditor, A4_W_MM, A4_H_MM, MM_TO_PX };
})(window);
