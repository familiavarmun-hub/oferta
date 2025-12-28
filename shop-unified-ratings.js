// shop-unified-ratings.js - Sistema de valoraciones unificado para SendVialo Shop

/**
 * Funciones para mostrar valoraciones unificadas en el shop
 */

// Generar laurel SVG según el tipo de insignia
function generateLaurelSVG(tipo, size = 120) {
    const colors = {
        diamond: { primary: '#e1f5fe', secondary: '#81d4fa', glow: '#4fc3f7' },
        gold: { primary: '#ffd700', secondary: '#ffb300', glow: '#ff8f00' },
        silver: { primary: '#e0e0e0', secondary: '#bdbdbd', glow: '#9e9e9e' },
        bronze: { primary: '#ffab91', secondary: '#ff8a65', glow: '#ff7043' },
        basic: { primary: '#f5f5f5', secondary: '#e0e0e0', glow: '#bdbdbd' }
    };
    
    const color = colors[tipo] || colors.basic;
    
    return `
        <svg width="${size}" height="${size}" viewBox="0 0 120 120" class="laurel-svg laurel-${tipo}">
            <defs>
                <radialGradient id="laurelGradient${tipo}" cx="50%" cy="30%">
                    <stop offset="0%" stop-color="${color.primary}" />
                    <stop offset="100%" stop-color="${color.secondary}" />
                </radialGradient>
                <filter id="glow${tipo}">
                    <feGaussianBlur stdDeviation="2" result="coloredBlur"/>
                    <feMerge> 
                        <feMergeNode in="coloredBlur"/>
                        <feMergeNode in="SourceGraphic"/>
                    </feMerge>
                </filter>
            </defs>
            <g fill="url(#laurelGradient${tipo})" stroke="${color.glow}" stroke-width="0.5" filter="url(#glow${tipo})">
                <path d="M30 45 Q20 35 25 25 Q35 20 45 30 Q50 35 45 45 Q35 50 30 45" />
                <path d="M90 45 Q100 35 95 25 Q85 20 75 30 Q70 35 75 45 Q85 50 90 45" />
                <path d="M35 55 Q25 45 30 35 Q40 30 50 40 Q55 45 50 55 Q40 60 35 55" />
                <path d="M85 55 Q95 45 90 35 Q80 30 70 40 Q65 45 70 55 Q80 60 85 55" />
                <path d="M40 65 Q30 55 35 45 Q45 40 55 50 Q60 55 55 65 Q45 70 40 65" />
                <path d="M80 65 Q90 55 85 45 Q75 40 65 50 Q60 55 65 65 Q75 70 80 65" />
                <path d="M45 75 Q35 65 40 55 Q50 50 60 60 Q65 65 60 75 Q50 80 45 75" />
                <path d="M75 75 Q85 65 80 55 Q70 50 60 60 Q55 65 60 75 Q70 80 75 75" />
            </g>
        </svg>
    `;
}

// Crear imagen de perfil con laurel y badge de verificación
function createProfileWithLaurel(imageUrl, rating, isVerified = false, size = 80) {
    const tipo = getBadgeType(rating);
    const laurelSize = size * 1.4;
    
    return `
        <div class="profile-image-laurel">
            <img src="${imageUrl}" alt="Perfil" width="${size}" height="${size}" 
                 onerror="this.onerror=null; this.src='user-default.jpg';">
            ${generateLaurelSVG(tipo, laurelSize)}
            ${isVerified ? '<div class="verified-badge"><i class="fas fa-check"></i></div>' : ''}
        </div>
    `;
}

// Determinar tipo de insignia según valoración
function getBadgeType(rating) {
    if (rating >= 4.8) return 'diamond';
    if (rating >= 4.5) return 'gold';
    if (rating >= 4.0) return 'silver';
    if (rating >= 3.5) return 'bronze';
    return 'basic';
}

// Generar estrellas para mostrar valoración
function generateStars(rating, showNumber = true) {
    const stars = [];
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 >= 0.5;
    
    for (let i = 0; i < 5; i++) {
        if (i < fullStars) {
            stars.push('<i class="fas fa-star"></i>');
        } else if (i === fullStars && hasHalfStar) {
            stars.push('<i class="fas fa-star-half-alt"></i>');
        } else {
            stars.push('<i class="far fa-star"></i>');
        }
    }
    
    const starsHtml = `<span class="rating-stars">${stars.join('')}</span>`;
    const numberHtml = showNumber ? `<span class="rating-value ${getBadgeType(rating)}-text">${rating}</span>` : '';
    
    return starsHtml + numberHtml;
}

// Renderizar información del vendedor en tarjetas de productos
function renderSellerInfo(seller, containerSelector) {
    const container = document.querySelector(containerSelector);
    if (!container) return;
    
    const rating = parseFloat(seller.seller_rating) || 0;
    const totalRatings = parseInt(seller.total_ratings) || 0;
    const isVerified = seller.seller_verified || false;
    
    // Usar avatar desde UI Avatars si no hay imagen personalizada
    let avatarUrl = seller.seller_avatar;
    if (!avatarUrl || avatarUrl.includes('user-default.jpg')) {
        const name = seller.seller_name || 'Usuario';
        avatarUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=667eea&color=fff&size=60`;
    }
    
    const html = `
        <div class="seller-info">
            <div class="seller-avatar">
                ${createProfileWithLaurel(avatarUrl, rating, isVerified, 50)}
            </div>
            <div class="seller-details">
                <h6>
                    ${seller.seller_name || seller.seller_username || 'Usuario'}
                    ${isVerified ? '<i class="fas fa-check-circle verified-icon" title="Usuario verificado"></i>' : ''}
                </h6>
                <div class="seller-rating">
                    ${rating > 0 ? generateStars(rating) : '<span class="no-rating">Sin valoraciones</span>'}
                    ${totalRatings > 0 ? `<span class="rating-count">(${totalRatings})</span>` : ''}
                </div>
            </div>
        </div>
    `;
    
    container.innerHTML = html;
}

// Actualizar estadísticas del vendedor (dashboard)
function updateSellerStats(stats) {
    const statsContainer = document.querySelector('.seller-stats-summary');
    if (!statsContainer) return;
    
    const rating = stats.average_rating || 0;
    const totalReviews = stats.total_reviews || 0;
    const badgeType = getBadgeType(rating);
    
    // Actualizar valoración promedio
    const ratingElement = statsContainer.querySelector('.rating-display');
    if (ratingElement) {
        ratingElement.innerHTML = `
            ${rating > 0 ? generateStars(rating) : '<span class="no-rating">Sin valoraciones</span>'}
            ${totalReviews > 0 ? `<span class="rating-count">(${totalReviews} valoraciones)</span>` : ''}
        `;
        ratingElement.className = `rating-display ${badgeType}-text`;
    }
    
    // Actualizar otros stats
    const statsElements = {
        'total-products': stats.total_products || 0,
        'active-products': stats.active_products || 0,
        'total-sales': stats.total_sales || 0,
        'total-revenue': `€${(stats.total_revenue || 0).toFixed(2)}`
    };
    
    for (const [elementClass, value] of Object.entries(statsElements)) {
        const element = statsContainer.querySelector(`.${elementClass}`);
        if (element) {
            element.textContent = value;
        }
    }
}

// Cargar y mostrar productos con valoraciones unificadas
function loadProductsWithRatings(filters = {}) {
    const loadingElement = document.querySelector('.products-loading');
    const productsContainer = document.querySelector('.products-grid');
    const paginationContainer = document.querySelector('.pagination-container');
    
    if (loadingElement) loadingElement.style.display = 'block';
    if (productsContainer) productsContainer.style.opacity = '0.5';
    
    // Construir query string con filtros
    const queryParams = new URLSearchParams({
        action: 'get_products',
        page: filters.page || 1,
        limit: filters.limit || 20,
        ...filters
    });
    
    fetch(`shop/shop-actions.php?${queryParams.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (loadingElement) loadingElement.style.display = 'none';
            if (productsContainer) productsContainer.style.opacity = '1';
            
            if (data.success && data.products) {
                renderProducts(data.products, productsContainer);
                renderPagination(data.pagination, paginationContainer);
            } else {
                console.error('Error loading products:', data.error);
                if (productsContainer) {
                    productsContainer.innerHTML = `
                        <div class="error-message">
                            <p>Error al cargar productos: ${data.error || 'Error desconocido'}</p>
                            <button onclick="loadProductsWithRatings()" class="retry-btn">Reintentar</button>
                        </div>
                    `;
                }
            }
        })
        .catch(error => {
            console.error('Error fetching products:', error);
            if (loadingElement) loadingElement.style.display = 'none';
            if (productsContainer) {
                productsContainer.style.opacity = '1';
                productsContainer.innerHTML = `
                    <div class="error-message">
                        <p>Error de conexión. Por favor, inténtalo de nuevo.</p>
                        <button onclick="loadProductsWithRatings()" class="retry-btn">Reintentar</button>
                    </div>
                `;
            }
        });
}

// Renderizar productos con información de vendedor unificada
function renderProducts(products, container) {
    if (!container) return;
    
    if (products.length === 0) {
        container.innerHTML = `
            <div class="no-products">
                <h3>No se encontraron productos</h3>
                <p>Intenta ajustar tus filtros de búsqueda</p>
            </div>
        `;
        return;
    }
    
    const productsHtml = products.map(product => {
        const rating = parseFloat(product.seller_rating) || 0;
        const totalRatings = parseInt(product.total_ratings) || 0;
        const isVerified = product.seller_verified || false;
        const badgeType = getBadgeType(rating);
        
        // Imagen del producto
        const productImage = product.primary_image || generatePlaceholderImage(product.category);
        
        // Avatar del vendedor
        let sellerAvatar = product.seller_avatar;
        if (!sellerAvatar || sellerAvatar.includes('user-default.jpg')) {
            const name = product.seller_name || 'Usuario';
            sellerAvatar = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=667eea&color=fff&size=50`;
        }
        
        return `
            <div class="product-card" data-product-id="${product.id}">
                <div class="product-image">
                    <img src="${productImage}" alt="${product.name}" loading="lazy">
                    ${product.trip_code ? `<div class="trip-badge">${product.trip_code}</div>` : ''}
                    ${product.low_stock ? '<div class="stock-badge low">Pocas unidades</div>' : ''}
                    ${!product.available ? '<div class="stock-badge out">Agotado</div>' : ''}
                </div>
                
                <div class="product-info">
                    <h3 class="product-name">${product.name}</h3>
                    <p class="product-description">${product.description.substring(0, 100)}${product.description.length > 100 ? '...' : ''}</p>
                    
                    <div class="product-price">
                        <span class="price-amount">${product.price.toFixed(2)}</span>
                        <span class="price-currency">${product.currency}</span>
                    </div>
                    
                    ${product.route_display ? `
                        <div class="product-route">
                            <i class="fas fa-route"></i>
                            <span>${product.route_display}</span>
                        </div>
                    ` : ''}
                    
                    <div class="seller-info">
                        <div class="seller-avatar">
                            ${createProfileWithLaurel(sellerAvatar, rating, isVerified, 40)}
                        </div>
                        <div class="seller-details">
                            <h6>
                                ${product.seller_name || product.seller_username || 'Usuario'}
                                ${isVerified ? '<i class="fas fa-check-circle verified-icon" title="Usuario verificado"></i>' : ''}
                            </h6>
                            <div class="seller-rating">
                                ${rating > 0 ? `
                                    <span class="rating-stars">${'★'.repeat(Math.floor(rating))}${'☆'.repeat(5-Math.floor(rating))}</span>
                                    <span class="rating-value ${badgeType}-text">${rating}</span>
                                    <span class="rating-count">(${totalRatings})</span>
                                ` : '<span class="no-rating">Sin valoraciones</span>'}
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="product-actions">
                    <button class="btn-add-cart ${!product.available ? 'disabled' : ''}" 
                            ${!product.available ? 'disabled' : ''}
                            onclick="addToCart(${product.id})">
                        ${!product.available ? 'Agotado' : 'Agregar al carrito'}
                    </button>
                    <button class="btn-view-details" onclick="viewProductDetails(${product.id})">
                        Ver detalles
                    </button>
                </div>
            </div>
        `;
    }).join('');
    
    container.innerHTML = productsHtml;
}

// Renderizar paginación
function renderPagination(pagination, container) {
    if (!container || !pagination) return;
    
    const { page, limit, total } = pagination;
    const totalPages = Math.ceil(total / limit);
    
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let paginationHtml = '<div class="pagination">';
    
    // Botón anterior
    if (page > 1) {
        paginationHtml += `<button onclick="changePage(${page - 1})" class="pagination-btn">
            <i class="fas fa-chevron-left"></i> Anterior
        </button>`;
    }
    
    // Números de página
    const startPage = Math.max(1, page - 2);
    const endPage = Math.min(totalPages, page + 2);
    
    if (startPage > 1) {
        paginationHtml += `<button onclick="changePage(1)" class="pagination-btn">1</button>`;
        if (startPage > 2) {
            paginationHtml += '<span class="pagination-dots">...</span>';
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        paginationHtml += `<button onclick="changePage(${i})" class="pagination-btn ${i === page ? 'active' : ''}">${i}</button>`;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            paginationHtml += '<span class="pagination-dots">...</span>';
        }
        paginationHtml += `<button onclick="changePage(${totalPages})" class="pagination-btn">${totalPages}</button>`;
    }
    
    // Botón siguiente
    if (page < totalPages) {
        paginationHtml += `<button onclick="changePage(${page + 1})" class="pagination-btn">
            Siguiente <i class="fas fa-chevron-right"></i>
        </button>`;
    }
    
    paginationHtml += '</div>';
    
    // Info de resultados
    const start = (page - 1) * limit + 1;
    const end = Math.min(page * limit, total);
    paginationHtml += `<div class="pagination-info">
        Mostrando ${start}-${end} de ${total} productos
    </div>`;
    
    container.innerHTML = paginationHtml;
}

// Cambiar página
function changePage(newPage) {
    const currentFilters = getCurrentFilters();
    currentFilters.page = newPage;
    loadProductsWithRatings(currentFilters);
    
    // Scroll hacia arriba
    document.querySelector('.products-section')?.scrollIntoView({ behavior: 'smooth' });
}

// Obtener filtros actuales del formulario
function getCurrentFilters() {
    const form = document.querySelector('.shop-filters-form');
    if (!form) return {};
    
    const formData = new FormData(form);
    const filters = {};
    
    for (let [key, value] of formData.entries()) {
        if (value && value.trim() !== '') {
            filters[key] = value.trim();
        }
    }
    
    return filters;
}

// Generar placeholder según categoría
function generatePlaceholderImage(category) {
    const placeholders = {
        food: 'https://images.unsplash.com/photo-1565299624946-3dc8b66b0e83?w=400&h=300&fit=crop&q=80',
        crafts: 'https://images.unsplash.com/photo-1578662015441-ce7ecf7fa773?w=400&h=300&fit=crop&q=80',
        fashion: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400&h=300&fit=crop&q=80',
        electronics: 'https://images.unsplash.com/photo-1581092335878-6e6f13b9a4f6?w=400&h=300&fit=crop&q=80',
        books: 'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?w=400&h=300&fit=crop&q=80',
        cosmetics: 'https://images.unsplash.com/photo-1596462502858-b3b5fe9a5c7a?w=400&h=300&fit=crop&q=80',
        toys: 'https://images.unsplash.com/photo-1558060370-d644479cb6f7?w=400&h=300&fit=crop&q=80',
        sports: 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=400&h=300&fit=crop&q=80',
        home: 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=400&h=300&fit=crop&q=80',
        others: 'https://images.unsplash.com/photo-1560472355-536de3962603?w=400&h=300&fit=crop&q=80'
    };
    
    return placeholders[category] || placeholders.others;
}

// Cargar estadísticas del vendedor (dashboard)
function loadSellerStats() {
    fetch('shop/shop-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_seller_stats'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.stats) {
            updateSellerStats(data.stats);
        } else {
            console.error('Error loading seller stats:', data.error);
        }
    })
    .catch(error => {
        console.error('Error fetching seller stats:', error);
    });
}

// Funciones del carrito (placeholders)
function addToCart(productId) {
    // Implementar lógica del carrito
    console.log('Adding product to cart:', productId);
}

function viewProductDetails(productId) {
    // Implementar vista de detalles del producto
    console.log('Viewing product details:', productId);
}

// Inicialización al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    console.log('🛍️ Shop unified ratings system initialized');
    
    // Cargar productos iniciales
    loadProductsWithRatings();
    
    // Cargar stats del vendedor si estamos en el dashboard
    if (document.querySelector('.seller-stats-summary')) {
        loadSellerStats();
    }
    
    // Event listeners para filtros
    const filtersForm = document.querySelector('.shop-filters-form');
    if (filtersForm) {
        filtersForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const filters = getCurrentFilters();
            filters.page = 1; // Reset página al filtrar
            loadProductsWithRatings(filters);
        });
        
        // Auto-filtrado al cambiar selects
        const autoFilterInputs = filtersForm.querySelectorAll('select, input[type="radio"]');
        autoFilterInputs.forEach(input => {
            input.addEventListener('change', function() {
                const filters = getCurrentFilters();
                filters.page = 1;
                loadProductsWithRatings(filters);
            });
        });
    }
    
    // Event listener para búsqueda
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const filters = getCurrentFilters();
                filters.page = 1;
                loadProductsWithRatings(filters);
            }, 500);
        });
    }
});

// Exportar funciones para uso global
window.ShopRatings = {
    loadProductsWithRatings,
    updateSellerStats,
    renderSellerInfo,
    createProfileWithLaurel,
    generateStars,
    getBadgeType,
    loadSellerStats,
    changePage
};