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
        const currency = info?.dataset.currency || '$';
        const t = (key) => (window.i18n && window.i18n[key]) || key;
        const fmt = (n) => currency + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const update = () => {
            const option = productSelect.selectedOptions[0];
            if (!option || !option.value) {
                info.innerHTML = `<span class="text-secondary">${t('Select a product to see its terms.')}</span>`;
                estimate.innerHTML = '';
                return;
            }
            const p = option.dataset;
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
            const term = parseInt(termInput.value, 10);
            if (!(principal > 0) || !(term > 0)) {
                estimate.innerHTML = '';
                return;
            }
            const r = parseFloat(p.rate) / 100 / parseInt(p.periods, 10);
            let installment;
            let totalInterest;
            if (p.method === 'flat') {
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
        });
    }
});
