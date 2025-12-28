/* ==========================================
   SENDVIALO - SISTEMA DE CARRITO COMPLETO
   ========================================== */

// ====================================
// ESTADO DEL CARRITO
// ====================================
let cart = JSON.parse(localStorage.getItem('sendvialo_cart')) || [];

// ====================================
// INICIALIZACIÓN DEL CARRITO
// ====================================
function initCart() {
    updateCartCount();
    
    // Event listeners para actualizar el carrito en tiempo real
    window.addEventListener('storage', function(e) {
        if (e.key === 'sendvialo_cart') {
            cart = JSON.parse(e.newValue) || [];
            updateCartCount();
        }
    });
}

// ====================================
// AÑADIR AL CARRITO
// ====================================
function addToCart(productId) {
    const product = allProducts.find(p => p.id === productId);
    if (!product) {
        showError('Producto no encontrado');
        return;
    }

    const existingItem = cart.find(item => item.id === productId);

    if (existingItem) {
        if (existingItem.quantity < product.stock) {
            existingItem.quantity++;
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Stock insuficiente',
                text: `Solo hay ${product.stock} unidades disponibles`,
                confirmButtonColor: '#41ba0d'
            });
            return;
        }
    } else {
        cart.push({ 
            ...product, 
            quantity: 1,
            addedAt: new Date().toISOString()
        });
    }

    saveCart();
    updateCartCount();
    showAddToCartFeedback(productId, product.name);
}

// ====================================
// ACTUALIZAR CANTIDAD
// ====================================
function updateQuantity(productId, newQuantity) {
    const item = cart.find(item => item.id === productId);
    const product = allProducts.find(p => p.id === productId);
    
    if (!item || !product) return;

    if (newQuantity <= 0) {
        removeFromCart(productId);
        return;
    }

    if (newQuantity > product.stock) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'warning',
            title: `Solo hay ${product.stock} unidades disponibles`,
            timer: 2000,
            showConfirmButton: false
        });
        return;
    }

    item.quantity = newQuantity;
    saveCart();
    updateCartCount();
    
    // Si el modal está abierto, actualizarlo
    if (document.querySelector('.cart-modal-overlay.active')) {
        renderCartModal();
    }
}

// ====================================
// ELIMINAR DEL CARRITO
// ====================================
function removeFromCart(productId) {
    Swal.fire({
        title: '¿Eliminar producto?',
        text: "Se quitará del carrito",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#41ba0d',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            cart = cart.filter(item => item.id !== productId);
            saveCart();
            updateCartCount();
            
            if (document.querySelector('.cart-modal-overlay.active')) {
                renderCartModal();
            }

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Producto eliminado',
                timer: 1500,
                showConfirmButton: false
            });
        }
    });
}

// ====================================
// VACIAR CARRITO
// ====================================
function clearCart() {
    if (cart.length === 0) return;

    Swal.fire({
        title: '¿Vaciar carrito?',
        text: "Se eliminarán todos los productos",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, vaciar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            cart = [];
            saveCart();
            updateCartCount();
            renderCartModal();

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Carrito vaciado',
                timer: 1500,
                showConfirmButton: false
            });
        }
    });
}

// ====================================
// GUARDAR CARRITO EN LOCALSTORAGE
// ====================================
function saveCart() {
    localStorage.setItem('sendvialo_cart', JSON.stringify(cart));
}

// ====================================
// ACTUALIZAR CONTADOR DEL CARRITO
// ====================================
function updateCartCount() {
    const count = cart.reduce((total, item) => total + item.quantity, 0);
    const badge = document.getElementById('cart-count');
    
    if (badge) {
        badge.textContent = count;
        
        // Animación del badge
        if (count > 0) {
            badge.style.transform = 'scale(1.3)';
            setTimeout(() => {
                badge.style.transform = 'scale(1)';
            }, 200);
        }
    }
}

// ====================================
// FEEDBACK VISUAL AL AÑADIR
// ====================================
function showAddToCartFeedback(productId, productName) {
    const btn = document.querySelector(`[data-product-id="${productId}"]`);
    
    if (btn) {
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> ¡Añadido!';
        btn.style.background = '#28a745';
        btn.disabled = true;
        
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.style.background = '';
            btn.disabled = false;
        }, 1500);
    }

    // Toast de confirmación
    Swal.fire({
        toast: true,
        position: 'bottom-end',
        icon: 'success',
        title: `${productName} añadido al carrito`,
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
    });
}

// ====================================
// CALCULAR TOTALES
// ====================================
function calculateCartTotals() {
    let totalItems = 0;
    let subtotal = 0;
    const itemsByMerchant = {};

    cart.forEach(item => {
        totalItems += item.quantity;
        subtotal += item.price * item.quantity;
        
        // Agrupar por vendedor
        if (!itemsByMerchant[item.seller_id]) {
            itemsByMerchant[item.seller_id] = {
                name: item.seller_name,
                items: [],
                subtotal: 0
            };
        }
        
        itemsByMerchant[item.seller_id].items.push(item);
        itemsByMerchant[item.seller_id].subtotal += item.price * item.quantity;
    });

    return {
        totalItems,
        subtotal,
        itemsByMerchant,
        total: subtotal // Aquí se pueden añadir impuestos, envío, etc.
    };
}

// ====================================
// ABRIR MODAL DEL CARRITO
// ====================================
function openCart() {
    renderCartModal();
    
    const overlay = document.getElementById('cart-modal-overlay');
    if (overlay) {
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

// ====================================
// CERRAR MODAL DEL CARRITO
// ====================================
function closeCart() {
    const overlay = document.getElementById('cart-modal-overlay');
    if (overlay) {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// ====================================
// RENDERIZAR MODAL DEL CARRITO
// ====================================
function renderCartModal() {
    let modalHTML = '';
    
    // Verificar si el modal ya existe
    let overlay = document.getElementById('cart-modal-overlay');
    
    if (!overlay) {
        // Crear el modal si no existe
        overlay = document.createElement('div');
        overlay.id = 'cart-modal-overlay';
        overlay.className = 'cart-modal-overlay';
        overlay.onclick = function(e) {
            if (e.target === overlay) closeCart();
        };
        document.body.appendChild(overlay);
    }

    if (cart.length === 0) {
        modalHTML = `
            <div class="cart-modal" onclick="event.stopPropagation()">
                <div class="cart-modal-header">
                    <h2>
                        <i class="fas fa-shopping-cart"></i>
                        Tu Carrito
                    </h2>
                    <button class="modal-close-btn" onclick="closeCart()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="cart-modal-body">
                    <div class="cart-empty">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Tu carrito está vacío</h3>
                        <p>Añade productos para empezar a comprar</p>
                        <button class="btn-continue-shopping" onclick="closeCart()">
                            <i class="fas fa-arrow-left"></i>
                            Seguir comprando
                        </button>
                    </div>
                </div>
            </div>
        `;
    } else {
        const totals = calculateCartTotals();
        
        modalHTML = `
            <div class="cart-modal" onclick="event.stopPropagation()">
                <div class="cart-modal-header">
                    <h2>
                        <i class="fas fa-shopping-cart"></i>
                        Tu Carrito
                        <span class="cart-count-badge">${totals.totalItems}</span>
                    </h2>
                    <button class="modal-close-btn" onclick="closeCart()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="cart-modal-body">
                    <div class="cart-items-list">
                        ${cart.map(item => `
                            <div class="cart-item" data-product-id="${item.id}">
                                <div class="cart-item-image">
                                    <img src="${item.primary_image || 'https://via.placeholder.com/100'}" 
                                         alt="${item.name}">
                                </div>
                                
                                <div class="cart-item-details">
                                    <h4 class="cart-item-name">${item.name}</h4>
                                    <p class="cart-item-seller">
                                        <i class="fas fa-user"></i>
                                        ${item.seller_name}
                                    </p>
                                    <p class="cart-item-price">
                                        ${formatPrice(item.price, item.currency)} × ${item.quantity}
                                    </p>
                                </div>
                                
                                <div class="cart-item-actions">
                                    <div class="quantity-controls">
                                        <button class="qty-btn" onclick="updateQuantity(${item.id}, ${item.quantity - 1})">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" 
                                               class="qty-input" 
                                               value="${item.quantity}" 
                                               min="1" 
                                               max="${item.stock}"
                                               onchange="updateQuantity(${item.id}, parseInt(this.value))">
                                        <button class="qty-btn" onclick="updateQuantity(${item.id}, ${item.quantity + 1})">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="cart-item-total">
                                        ${formatPrice(item.price * item.quantity, item.currency)}
                                    </div>
                                    
                                    <button class="btn-remove-item" onclick="removeFromCart(${item.id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                
                <div class="cart-modal-footer">
                    <div class="cart-summary">
                        <div class="summary-row">
                            <span>Subtotal (${totals.totalItems} productos):</span>
                            <strong>${formatCartTotal(totals.subtotal)}</strong>
                        </div>
                        <div class="summary-row total">
                            <span>Total:</span>
                            <strong class="total-amount">${formatCartTotal(totals.total)}</strong>
                        </div>
                    </div>
                    
                    <div class="cart-actions">
                        <button class="btn-clear-cart" onclick="clearCart()">
                            <i class="fas fa-trash-alt"></i>
                            Vaciar carrito
                        </button>
                        <button class="btn-checkout" onclick="proceedToCheckout()">
                            <i class="fas fa-credit-card"></i>
                            Proceder al pago
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
    overlay.innerHTML = modalHTML;
}

// ====================================
// FORMATEAR TOTAL DEL CARRITO
// ====================================
function formatCartTotal(amount) {
    // Si hay múltiples monedas, mostrar el total por moneda
    const currencies = {};
    
    cart.forEach(item => {
        if (!currencies[item.currency]) {
            currencies[item.currency] = 0;
        }
        currencies[item.currency] += item.price * item.quantity;
    });
    
    const currencySymbols = {
        'EUR': '€', 'USD': '$', 'BOB': 'Bs.', 'BRL': 'R$',
        'ARS': '$', 'VES': 'Bs.', 'COP': '$', 'MXN': '$',
        'NIO': 'C$', 'CUP': '$MN', 'PEN': 'S/'
    };
    
    return Object.entries(currencies).map(([currency, total]) => {
        const symbol = currencySymbols[currency] || currency;
        return `${symbol}${total.toFixed(2)}`;
    }).join(' + ');
}

// ====================================
// PROCEDER AL CHECKOUT
// ====================================
function proceedToCheckout() {
    if (cart.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Carrito vacío',
            text: 'Añade productos antes de proceder al pago',
            confirmButtonColor: '#41ba0d'
        });
        return;
    }

    const totals = calculateCartTotals();
    
    Swal.fire({
        title: '<i class="fas fa-receipt"></i> Resumen de compra',
        html: `
            <div style="text-align: left; padding: 1rem;">
                <h4 style="margin-bottom: 1rem; color: #374151;">Productos (${totals.totalItems}):</h4>
                ${cart.map(item => `
                    <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #e5e7eb;">
                        <span>${item.name} x${item.quantity}</span>
                        <strong>${formatPrice(item.price * item.quantity, item.currency)}</strong>
                    </div>
                `).join('')}
                
                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 2px solid #41ba0d;">
                    <div style="display: flex; justify-content: space-between; font-size: 1.2rem;">
                        <strong>Total:</strong>
                        <strong style="color: #41ba0d;">${formatCartTotal(totals.total)}</strong>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem; padding: 1rem; background: #f9fafb; border-radius: 8px;">
                    <p style="margin: 0; color: #6b7280; font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> 
                        El pago se procesará de forma segura
                    </p>
                </div>
            </div>
        `,
        width: '600px',
        showCancelButton: true,
        confirmButtonColor: '#41ba0d',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-check"></i> Confirmar compra',
        cancelButtonText: '<i class="fas fa-arrow-left"></i> Volver',
        customClass: {
            confirmButton: 'swal-confirm-btn',
            cancelButton: 'swal-cancel-btn'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            processCheckout();
        }
    });
}

// ====================================
// PROCESAR CHECKOUT
// ====================================
async function processCheckout() {
    // Mostrar loading
    Swal.fire({
        title: 'Procesando compra...',
        html: 'Por favor espera',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        // Aquí iría la llamada al backend para procesar el pago
        // Simulamos un delay
        await new Promise(resolve => setTimeout(resolve, 2000));

        // Simular respuesta exitosa
        const orderNumber = 'ORD-' + Date.now();
        
        Swal.fire({
            icon: 'success',
            title: '¡Compra exitosa!',
            html: `
                <div style="text-align: center; padding: 1rem;">
                    <p style="font-size: 1.1rem; margin-bottom: 1rem;">
                        Tu pedido ha sido procesado correctamente
                    </p>
                    <div style="background: #f9fafb; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <p style="margin: 0; color: #6b7280;">Número de orden:</p>
                        <strong style="font-size: 1.2rem; color: #41ba0d;">${orderNumber}</strong>
                    </div>
                    <p style="color: #6b7280; font-size: 0.9rem;">
                        Recibirás un email con los detalles de tu compra
                    </p>
                </div>
            `,
            confirmButtonColor: '#41ba0d',
            confirmButtonText: 'Entendido'
        }).then(() => {
            // Vaciar carrito después de la compra exitosa
            cart = [];
            saveCart();
            updateCartCount();
            closeCart();
        });

    } catch (error) {
        console.error('Error en checkout:', error);
        
        Swal.fire({
            icon: 'error',
            title: 'Error en el pago',
            text: 'No se pudo procesar tu compra. Por favor, intenta nuevamente.',
            confirmButtonColor: '#41ba0d'
        });
    }
}

// ====================================
// FORMATEAR PRECIO
// ====================================
function formatPrice(amount, currency) {
    const symbols = {
        'EUR': '€', 'USD': '$', 'BOB': 'Bs.', 'BRL': 'R$',
        'ARS': '$', 'VES': 'Bs.', 'COP': '$', 'MXN': '$',
        'NIO': 'C$', 'CUP': '$MN', 'PEN': 'S/'
    };
    
    const symbol = symbols[currency] || currency;
    const price = parseFloat(amount).toFixed(2);
    return `${symbol}${price}`;
}

// ====================================
// CERRAR CON ESC
// ====================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const cartModal = document.querySelector('.cart-modal-overlay.active');
        if (cartModal) {
            closeCart();
        }
    }
});

// ====================================
// INICIALIZAR AL CARGAR LA PÁGINA
// ====================================
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCart);
} else {
    initCart();
}