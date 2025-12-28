// shop-currency-integration.js
// Integración del sistema de monedas existente con SendVialo Shop
(function () {
    console.log('🛍️ SendVialo Shop Currency Handler iniciado');
  
    // Configuración de monedas del Shop (puedes ampliar)
    const SHOP_CURRENCIES = {
      'EUR': { symbol: '€', name: 'Euros', position: 'after', rate: 1.0 },   // base
      'USD': { symbol: '$', name: 'Dólares', position: 'before', rate: 1.18 } // ejemplo
    };
  
    // Elementos del DOM
    const el = {
      selector: null,
      display: null,
      input: null,
      priceInputs: null,
      totalDisplays: null,
      cartTotal: null
    };
  
    let currentShopCurrency = localStorage.getItem('sendvialo_shop_currency') || 'EUR';
  
    function initDomRefs() {
      el.selector = document.getElementById('shop-currency-selector');
      el.display = document.querySelectorAll('.shop-currency-display');
      el.input = document.getElementById('shop-currency-input');
      el.priceInputs = document.querySelectorAll('.price-input');
      el.totalDisplays = document.querySelectorAll('.total-amount');
      el.cartTotal = document.getElementById('cart-total-amount');
  
      console.log('🔍 Elementos del Shop:', {
        selector: !!el.selector,
        displays: el.display.length,
        input: !!el.input,
        priceInputs: el.priceInputs.length,
        totalDisplays: el.totalDisplays.length,
        cartTotal: !!el.cartTotal
      });
    }
  
    function formatPrice(amount, currencyCode = currentShopCurrency) {
      const c = SHOP_CURRENCIES[currencyCode];
      if (!c) return `${amount}`;
      const v = Number(amount).toFixed(2);
      return c.position === 'before' ? `${c.symbol}${v}` : `${v}${c.symbol}`;
    }
  
    function convertPrice(amount, fromCurrency, toCurrency) {
      if (!fromCurrency) fromCurrency = 'EUR';
      if (!toCurrency) toCurrency = currentShopCurrency;
      if (fromCurrency === toCurrency) return amount;
      const fromRate = SHOP_CURRENCIES[fromCurrency]?.rate ?? 1;
      const toRate = SHOP_CURRENCIES[toCurrency]?.rate ?? 1;
      const eurAmount = Number(amount) / fromRate;
      return eurAmount * toRate;
    }
  
    function updateTotalDisplays() {
      el.totalDisplays.forEach(t => {
        const raw = parseFloat(t.dataset.originalTotal || t.textContent.replace(/[^\d.]/g, ''));
        const originalCurrency = t.dataset.originalCurrency || 'EUR';
        if (!isNaN(raw)) {
          const conv = convertPrice(raw, originalCurrency, currentShopCurrency);
          t.textContent = formatPrice(conv);
        }
      });
    }
  
    function updateAllPrices() {
      document.querySelectorAll('.product-price').forEach(p => {
        const originalPrice = parseFloat(p.dataset.originalPrice || p.dataset.price);
        const originalCurrency = p.dataset.originalCurrency || 'EUR';
        if (!isNaN(originalPrice)) {
          const conv = convertPrice(originalPrice, originalCurrency, currentShopCurrency);
          p.textContent = formatPrice(conv);
          p.dataset.convertedPrice = conv;
        }
      });
  
      el.priceInputs.forEach(input => {
        const originalPrice = parseFloat(input.dataset.originalPrice || input.value);
        const originalCurrency = input.dataset.originalCurrency || 'EUR';
        if (!isNaN(originalPrice) && input.value) {
          const conv = convertPrice(originalPrice, originalCurrency, currentShopCurrency);
          input.value = conv.toFixed(2);
        }
      });
  
      updateTotalDisplays();
    }
  
    function updateCartCurrency() {
      const cart = JSON.parse(localStorage.getItem('sendvialo_cart') || '[]');
      let total = 0;
      cart.forEach(item => {
        const conv = convertPrice(item.price, item.currency, currentShopCurrency);
        total += conv * item.quantity;
        item.convertedPrice = conv;
        item.displayCurrency = currentShopCurrency;
      });
      if (el.cartTotal) el.cartTotal.textContent = formatPrice(total);
      const count = document.getElementById('cart-count');
      if (count && total > 0) count.title = `Total: ${formatPrice(total)}`;
      window.dispatchEvent(new CustomEvent('cartCurrencyUpdated', {
        detail: { total, currency: currentShopCurrency }
      }));
    }
  
    // *** CORREGIDO: integrateWithMainCurrencySystem (antes estaba mal escrito) ***
    function integrateWithMainCurrencySystem() {
      // Si existe un sistema principal de moneda, sincroniza
      const mainCurrencySelector = document.getElementById('currency-selector');
      if (mainCurrencySelector) {
        const val = mainCurrencySelector.value;
        if (SHOP_CURRENCIES[val]) setCurrency(val);
        mainCurrencySelector.addEventListener('change', function () {
          if (SHOP_CURRENCIES[this.value]) setCurrency(this.value);
        });
      }
    }
  
    function setCurrency(code) {
      const c = SHOP_CURRENCIES[code];
      if (!c) return console.warn('Moneda no soportada:', code);
      currentShopCurrency = code;
  
      if (el.selector) el.selector.value = code;
      if (el.input) el.input.value = code;
  
      document.querySelectorAll('.shop-currency-display').forEach(d => {
        d.textContent = c.symbol;
        d.setAttribute('data-currency', code);
      });
  
      updateAllPrices();
      updateCartCurrency();
      localStorage.setItem('sendvialo_shop_currency', code);
  
      window.dispatchEvent(new CustomEvent('shopCurrencyChanged', {
        detail: { currency: code, symbol: c.symbol }
      }));
    }
  
    function setupShopCurrencySelector() {
      if (!el.selector) return;
      el.selector.innerHTML = '';
      Object.entries(SHOP_CURRENCIES).forEach(([code, c]) => {
        const opt = document.createElement('option');
        opt.value = code;
        opt.textContent = `${c.symbol} ${c.name}`;
        el.selector.appendChild(opt);
      });
      el.selector.value = currentShopCurrency;
      el.selector.addEventListener('change', function () { setCurrency(this.value); });
    }
  
    function setupPriceInputs() {
      el.priceInputs.forEach(input => {
        if (input.value) {
          input.dataset.originalPrice = input.value;
          input.dataset.originalCurrency = currentShopCurrency;
        }
        input.addEventListener('blur', function () {
          if (this.value) this.value = parseFloat(this.value).toFixed(2);
        });
      });
    }
  
    function setupDynamicListeners() {
      const obs = new MutationObserver(muts => {
        muts.forEach(m => {
          if (m.type === 'childList') {
            m.addedNodes.forEach(node => {
              if (node.nodeType === 1) {
                const news = node.querySelectorAll?.('.product-price, .price-input, .total-amount') || [];
                if (news.length > 0) setTimeout(updateAllPrices, 100);
              }
            });
          }
        });
      });
      obs.observe(document.body, { childList: true, subtree: true });
    }
  
    function initialize() {
      initDomRefs();
      setupShopCurrencySelector();
      setupPriceInputs();
      setCurrency(currentShopCurrency);
      integrateWithMainCurrencySystem();
      setupDynamicListeners();
      window.addEventListener('cartUpdated', updateCartCurrency);
      console.log('✅ Sistema de monedas del Shop inicializado');
    }
  
    // API pública
    window.ShopCurrency = {
      getCurrentCurrency: () => currentShopCurrency,
      setCurrency,
      formatPrice,
      convertPrice,
      getCurrencyInfo: code => SHOP_CURRENCIES[code],
      updatePrices: updateAllPrices,
      debug: function () {
        console.log('🛍️ Estado', { currentShopCurrency, el, SHOP_CURRENCIES });
        const cart = JSON.parse(localStorage.getItem('sendvialo_cart') || '[]');
        if (cart.length) {
          const total = cart.reduce((s, i) => s + convertPrice(i.price, i.currency, currentShopCurrency) * i.quantity, 0);
          console.log('Total carrito:', formatPrice(total));
        }
      }
    };
  
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initialize);
    } else {
      initialize();
    }
  })();
  