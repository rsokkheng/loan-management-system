import * as bootstrap from 'bootstrap';
import 'admin-lte';
import { OverlayScrollbars } from 'overlayscrollbars';
import Chart from 'chart.js/auto';

window.bootstrap = bootstrap;
window.Chart = Chart;

document.addEventListener('DOMContentLoaded', () => {
    // Custom scrollbar on the sidebar (desktop only, as recommended by AdminLTE).
    const sidebarWrapper = document.querySelector('.sidebar-wrapper');
    if (sidebarWrapper && window.innerWidth > 992) {
        OverlayScrollbars(sidebarWrapper, {
            scrollbars: { theme: 'os-theme-light', autoHide: 'leave', clickScroll: true },
        });
    }

    // Light / dark theme toggle, remembered per browser.
    const applyTheme = (theme) => {
        document.documentElement.setAttribute('data-bs-theme', theme);
        document.querySelectorAll('[data-theme-icon]').forEach((icon) => {
            icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        });
    };
    applyTheme(localStorage.getItem('theme') || 'light');
    document.querySelectorAll('[data-toggle-theme]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            try { localStorage.setItem('theme', next); } catch (e) { /* storage unavailable */ }
            applyTheme(next);
        });
    });

    // Confirmation modal for destructive forms: <form data-confirm="Message">
    const confirmModalEl = document.getElementById('confirmModal');
    if (confirmModalEl) {
        const confirmModal = new bootstrap.Modal(confirmModalEl);
        let pendingForm = null;

        document.querySelectorAll('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.confirmed === 'true') {
                    return;
                }
                event.preventDefault();
                pendingForm = form;
                confirmModalEl.querySelector('[data-confirm-message]').textContent = form.dataset.confirm;
                confirmModal.show();
            });
        });

        confirmModalEl.querySelector('[data-confirm-accept]').addEventListener('click', () => {
            if (pendingForm) {
                pendingForm.dataset.confirmed = 'true';
                confirmModal.hide();
                pendingForm.requestSubmit();
            }
        });
    }

    // Deep link from "Receive Payment" list: loans/{id}#receive opens the payment modal.
    const paymentModalEl = document.getElementById('paymentModal');
    if (paymentModalEl && window.location.hash === '#receive') {
        bootstrap.Modal.getOrCreateInstance(paymentModalEl).show();
    }

    // After a failed submit, reopen the modal the form came from (forms send a hidden _modal field).
    const failedModal = document.body.dataset.reopenModal && document.getElementById(document.body.dataset.reopenModal);
    if (failedModal) {
        bootstrap.Modal.getOrCreateInstance(failedModal).show();
    }

    // Auto-dismiss toasts.
    document.querySelectorAll('.toast').forEach((el) => new bootstrap.Toast(el, { delay: 5000 }).show());

    // Tooltips.
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));

    // Loan application form: show product limits and a live estimate.
    const productSelect = document.getElementById('loan_product_id');
    if (productSelect) {
        const amountInput = document.getElementById('principal_amount');
        const termInput = document.getElementById('term');
        const info = document.getElementById('product-info');
        const estimate = document.getElementById('loan-estimate');
        const t = (key) => (window.i18n && window.i18n[key]) || key;
        let currency = { symbol: '$', precision: 2, suffix: false };
        const fmt = (n) => {
            const number = Number(n).toLocaleString(undefined, { minimumFractionDigits: currency.precision, maximumFractionDigits: currency.precision });
            return currency.suffix ? number + currency.symbol : currency.symbol + number;
        };

        // Flexible schedule editor: rows of due date + principal.
        const planBox = document.getElementById('flexible-plan');
        const planRows = planBox?.querySelector('[data-plan-rows]');
        const termWrapper = termInput.closest('[class*="mb-3"]');
        const rows = () => [...planRows.querySelectorAll('[data-plan-row]')];
        const renumber = () => rows().forEach((row, i) => {
            row.querySelector('[data-plan-number]').textContent = i + 1;
            row.querySelector('[data-plan-date]').name = `plan[${i}][due_date]`;
            row.querySelector('[data-plan-principal]').name = `plan[${i}][principal]`;
        });
        const planTotal = () => rows().reduce((sum, row) => sum + (parseFloat(row.querySelector('[data-plan-principal]').value) || 0), 0);
        const showPlanTotal = () => {
            const total = planTotal();
            const cell = planBox.querySelector('[data-plan-total]');
            cell.textContent = fmt(total);
            cell.classList.toggle('text-danger', Math.abs(total - (parseFloat(amountInput.value) || 0)) > 0.005);
        };
        const setFlexible = (isFlexible) => {
            planBox.hidden = !isFlexible;
            planBox.querySelectorAll('input').forEach((input) => { input.disabled = !isFlexible; });
            termWrapper.hidden = isFlexible;
            termInput.required = !isFlexible;
        };

        planBox?.querySelector('[data-plan-add]').addEventListener('click', () => {
            const last = rows().at(-1);
            const copy = last.cloneNode(true);
            copy.querySelectorAll('input').forEach((input) => { input.value = ''; input.classList.remove('is-invalid'); });
            copy.querySelectorAll('.invalid-feedback').forEach((el) => el.remove());
            planRows.append(copy);
            renumber();
            update();
        });
        planRows?.addEventListener('click', (event) => {
            if (event.target.closest('[data-plan-remove]') && rows().length > 1) {
                event.target.closest('[data-plan-row]').remove();
                renumber();
                update();
            }
        });
        planRows?.addEventListener('input', () => update());
        planBox?.querySelector('[data-plan-spread]').addEventListener('click', () => {
            const amount = parseFloat(amountInput.value) || 0;
            const count = rows().length;
            const factor = 10 ** currency.precision;
            const share = Math.floor((amount / count) * factor) / factor;
            rows().forEach((row, i) => {
                const value = i === count - 1 ? amount - share * (count - 1) : share;
                row.querySelector('[data-plan-principal]').value = value.toFixed(currency.precision);
            });
            update();
        });

        const update = () => {
            const option = productSelect.selectedOptions[0];
            if (planBox) {
                setFlexible(option?.dataset.schedule === 'flexible');
            }
            if (!option || !option.value) {
                info.innerHTML = `<span class="text-secondary">${t('Select a product to see its terms.')}</span>`;
                estimate.innerHTML = '';
                return;
            }
            const p = option.dataset;
            currency = { symbol: p.currencySymbol, precision: parseInt(p.currencyPrecision, 10), suffix: p.currencySuffix === '1' };
            const symbol = amountInput.previousElementSibling;
            if (symbol?.classList.contains('input-group-text')) {
                symbol.textContent = currency.symbol;
            }
            amountInput.step = currency.precision > 0 ? (1 / 10 ** currency.precision).toString() : '1';
            amountInput.min = p.minAmount;
            amountInput.max = p.maxAmount;
            termInput.min = p.minTerm;
            termInput.max = p.maxTerm;
            info.innerHTML = `
                <div class="row g-2 small">
                    <div class="col-6"><span class="text-secondary">${t('Interest')}</span><br><strong>${p.rate}% ${t('p.a.')} · ${p.methodLabel}</strong></div>
                    <div class="col-6"><span class="text-secondary">${t('Repayment')}</span><br><strong>${p.frequencyLabel}</strong></div>
                    <div class="col-6"><span class="text-secondary">${t('Amount')}</span><br><strong>${fmt(p.minAmount)} – ${fmt(p.maxAmount)}</strong></div>
                    <div class="col-6"><span class="text-secondary">${t('Term')}</span><br><strong>${p.minTerm} – ${p.maxTerm} ${t('installments')}</strong></div>
                    <div class="col-6"><span class="text-secondary">${t('Processing fee')}</span><br><strong>${p.fee}%</strong></div>
                    <div class="col-6"><span class="text-secondary">${t('Late penalty')}</span><br><strong>${p.penalty}% ${t('after')} ${p.grace} ${t('day(s)')}</strong></div>
                </div>`;

            const principal = parseFloat(amountInput.value);
            const isFlexible = p.schedule === 'flexible';
            if (isFlexible) {
                showPlanTotal();
            }
            const term = isFlexible ? rows().length : parseInt(termInput.value, 10);
            if (!(principal > 0) || !(term > 0)) {
                estimate.innerHTML = '';
                return;
            }
            const r = parseFloat(p.rate) / 100 / parseInt(p.periods, 10);
            let installment;
            let totalInterest;
            if (isFlexible) {
                // Actual days on a 365-day year, from the application date.
                const daily = parseFloat(p.rate) / 100 / 365;
                let start = new Date(document.getElementById('application_date').value);
                let balance = principal;
                totalInterest = 0;
                rows().forEach((row) => {
                    const due = new Date(row.querySelector('[data-plan-date]').value);
                    const days = Math.max(0, (due - start) / 86400000) || 0;
                    totalInterest += (p.method === 'flat' ? principal : balance) * daily * days;
                    balance -= parseFloat(row.querySelector('[data-plan-principal]').value) || 0;
                    if (!Number.isNaN(due.getTime())) {
                        start = due;
                    }
                });
                installment = (principal + totalInterest) / term;
            } else if (p.method === 'flat') {
                totalInterest = principal * r * term;
                installment = (principal + totalInterest) / term;
            } else {
                installment = r > 0 ? principal * r / (1 - Math.pow(1 + r, -term)) : principal / term;
                totalInterest = installment * term - principal;
            }
            const fee = principal * parseFloat(p.fee) / 100;
            estimate.innerHTML = `
                <div class="row text-center g-2">
                    <div class="col-6 col-md-3"><div class="stat-label">${t('Installment')}</div><div class="fs-5 fw-semibold">${fmt(installment)}</div></div>
                    <div class="col-6 col-md-3"><div class="stat-label">${t('Total interest')}</div><div class="fs-5 fw-semibold">${fmt(totalInterest)}</div></div>
                    <div class="col-6 col-md-3"><div class="stat-label">${t('Total payable')}</div><div class="fs-5 fw-semibold">${fmt(principal + totalInterest)}</div></div>
                    <div class="col-6 col-md-3"><div class="stat-label">${t('Net disbursed')}</div><div class="fs-5 fw-semibold">${fmt(principal - fee)}</div></div>
                </div>
                <p class="small text-secondary mb-0 mt-2">${t('Estimate only. The final schedule is generated at disbursement.')}</p>`;
        };

        [productSelect, amountInput, termInput].forEach((el) => el.addEventListener('input', update));
        update();
    }

    // Cascading address: province → district → commune → village.
    document.querySelectorAll('[data-address-fields]').forEach((group) => {
        const select = (name) => group.querySelector(`select[name="${name}"]`);

        const reset = (field) => {
            const placeholder = field.querySelector('option[value=""]');
            field.replaceChildren(...(placeholder ? [placeholder] : []));
            field.value = '';
        };

        group.querySelectorAll('select[data-location-child]').forEach((parent) => {
            parent.addEventListener('change', async () => {
                // Clear every level below the one that changed.
                let child = select(parent.dataset.locationChild);
                const first = child;
                while (child) {
                    reset(child);
                    child = child.dataset.locationChild ? select(child.dataset.locationChild) : null;
                }

                if (!parent.value || !first?.dataset.locationUrl) {
                    return;
                }

                first.disabled = true;
                try {
                    const response = await fetch(`${first.dataset.locationUrl}?parent=${encodeURIComponent(parent.value)}`, {
                        headers: { Accept: 'application/json' },
                    });
                    const options = response.ok ? await response.json() : [];
                    options.forEach(({ id, name }) => first.append(new Option(name, id)));
                } finally {
                    first.disabled = false;
                }
            });
        });
    });

    // Calculator: pre-fill fields from a chosen product.
    const calcProduct = document.getElementById('calc-product');
    if (calcProduct) {
        calcProduct.addEventListener('change', () => {
            const p = calcProduct.selectedOptions[0]?.dataset;
            if (!p || !p.rate) return;
            document.getElementById('interest_rate').value = p.rate;
            document.getElementById('interest_method').value = p.method;
            document.getElementById('repayment_frequency').value = p.frequency;
            document.getElementById('processing_fee_percent').value = p.fee;
            document.getElementById('currency').value = p.currency;
        });
    }
});
