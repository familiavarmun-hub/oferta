<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SendVialo Shop - Productos de Viajeros</title>
    <link rel="stylesheet" href="shop-optimized.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="icon" href="../Imagenes/globo5.png" type="image/png">
</head>
<body>
    <!-- Header dinámico -->
    <?php if (file_exists('../header.php')): ?>
        <?php include '../header.php'; ?>
    <?php endif; ?>

    <!-- HERO SECTION - Patrón F: Lo más importante arriba -->
    <div class="hero-section">
        <div class="hero-content">
            <div class="hero-text">
                <h1><i class="fas fa-globe-americas"></i> <strong>Productos únicos</strong> del mundo</h1>
                <p class="hero-subtitle">Compra directamente a viajeros verificados</p>
                
                <!-- BARRA DE BÚSQUEDA PRINCIPAL EN HERO - Siempre visible al inicio -->
                <div class="search-hero">
                    <i class="fas fa-search"></i>
                    <input type="text" id="hero-search" placeholder="¿Qué producto buscas? Ej: perfume francés, chocolate suizo...">
                    <button class="search-btn" onclick="quickSearch()">Buscar</button>
                </div>

                <!-- INDICADORES DE CONFIANZA - Psicología de confianza -->
                <div class="trust-badges">
                    <div class="trust-item">
                        <i class="fas fa-shield-alt"></i>
                        <span><strong>Compra segura</strong></span>
                    </div>
                    <div class="trust-item">
                        <i class="fas fa-user-check"></i>
                        <span><strong>Viajeros verificados</strong></span>
                    </div>
                    <div class="trust-item">
                        <i class="fas fa-star"></i>
                        <span><strong>+500 reseñas</strong> 4.8/5</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- BARRA DE BÚSQUEDA STICKY - Aparece al hacer scroll (segundo buscador) -->
    <div class="search-sticky-wrapper" id="search-sticky-wrapper">
        <div class="container">
            <div class="search-sticky">
                <div class="search-sticky-content">
                    <i class="fas fa-search"></i>
                    <input type="text" id="sticky-search" placeholder="¿Qué producto buscas?">
                    <button class="search-btn-sticky" onclick="quickSearch()">
                        <i class="fas fa-search"></i>
                        <span>Buscar</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTROS EN MODAL - Sistema limpio -->
    <div class="filters-section">
        <div class="container">
            <button class="filters-trigger-btn" onclick="openFiltersModal()">
                <i class="fas fa-filter"></i>
                <span>Filtros</span>
                <span class="active-filters-count" id="active-filters-count" style="display: none;">0</span>
            </button>
        </div>
    </div>

    <!-- MODAL DE FILTROS -->
    <div class="filters-modal-overlay" id="filters-modal-overlay" onclick="closeFiltersModal()">
        <div class="filters-modal" onclick="event.stopPropagation()">
            <!-- Header del Modal -->
            <div class="filters-modal-header">
                <h2>
                    <i class="fas fa-sliders-h"></i>
                    Filtrar productos
                </h2>
                <button class="modal-close-btn" onclick="closeFiltersModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Contenido del Modal -->
            <div class="filters-modal-body">
                <!-- Categorías -->
                <div class="filter-group">
                    <label class="filter-group-label">
                        <i class="fas fa-th"></i>
                        Categoría
                    </label>
                    <div class="filter-options-grid">
                        <button class="filter-option active" data-category="all" onclick="selectCategory(this, 'all')">
                            <i class="fas fa-th"></i>
                            <span>Todo</span>
                        </button>
                        <button class="filter-option" data-category="food" onclick="selectCategory(this, 'food')">
                            <i class="fas fa-cookie-bite"></i>
                            <span>Comida</span>
                        </button>
                        <button class="filter-option" data-category="crafts" onclick="selectCategory(this, 'crafts')">
                            <i class="fas fa-palette"></i>
                            <span>Artesanías</span>
                        </button>
                        <button class="filter-option" data-category="fashion" onclick="selectCategory(this, 'fashion')">
                            <i class="fas fa-tshirt"></i>
                            <span>Moda</span>
                        </button>
                        <button class="filter-option" data-category="cosmetics" onclick="selectCategory(this, 'cosmetics')">
                            <i class="fas fa-spray-can"></i>
                            <span>Cosméticos</span>
                        </button>
                    </div>
                </div>

                <!-- Origen -->
                <div class="filter-group">
                    <label class="filter-group-label">
                        <i class="fas fa-map-marker-alt"></i>
                        País de origen
                    </label>
                    <select id="origin-modal" class="filter-select">
                        <option value="">Cualquier país</option>
                        <option value="spain">🇪🇸 España</option>
                        <option value="france">🇫🇷 Francia</option>
                        <option value="italy">🇮🇹 Italia</option>
                        <option value="mexico">🇲🇽 México</option>
                        <option value="bolivia">🇧🇴 Bolivia</option>
                        <option value="peru">🇵🇪 Perú</option>
                    </select>
                </div>

                <!-- Destino -->
                <div class="filter-group">
                    <label class="filter-group-label">
                        <i class="fas fa-plane-arrival"></i>
                        Ciudad de destino
                    </label>
                    <select id="destination-modal" class="filter-select">
                        <option value="">Cualquier ciudad</option>
                        <option value="barcelona">Barcelona</option>
                        <option value="madrid">Madrid</option>
                        <option value="bilbao">Bilbao</option>
                        <option value="lima">Lima</option>
                        <option value="la-paz">La Paz</option>
                    </select>
                </div>

                <!-- Moneda -->
                <div class="filter-group">
                    <label class="filter-group-label">
                        <i class="fas fa-coins"></i>
                        Moneda
                    </label>
                    <select id="currency-modal" class="filter-select">
                        <option value="">Todas las monedas</option>
                        <option value="EUR">💶 EUR (€)</option>
                        <option value="USD">💵 USD ($)</option>
                        <option value="BOB">💰 BOB (Bs.)</option>
                    </select>
                </div>
            </div>

            <!-- Footer del Modal -->
            <div class="filters-modal-footer">
                <button class="btn-reset-filters" onclick="resetFiltersModal()">
                    <i class="fas fa-redo"></i>
                    Limpiar todo
                </button>
                <button class="btn-apply-filters" onclick="applyFiltersModal()">
                    <i class="fas fa-check"></i>
                    Aplicar filtros
                </button>
            </div>
        </div>
    </div>

    <!-- PRODUCTOS - Diseño escaneable (Nielsen) -->
    <div class="products-section" id="products-section">
        <div class="container">
            <!-- Indicador de resultados - Feedback inmediato -->
            <div class="results-bar" id="results-bar">
                <div class="results-info">
                    <i class="fas fa-check-circle"></i>
                    <span><strong id="products-count">0</strong> productos encontrados</span>
                    <span class="search-term" id="search-term-display"></span>
                </div>
                <div class="results-sort">
                    <label>Ordenar:</label>
                    <select id="sort-by" onchange="sortProducts(this.value)">
                        <option value="relevance">Relevancia</option>
                        <option value="price-low">Precio: menor a mayor</option>
                        <option value="price-high">Precio: mayor a menor</option>
                        <option value="rating">Mejor valorados</option>
                    </select>
                </div>
            </div>

            <!-- Grid de productos -->
            <div class="products-grid" id="products-grid">
                <!-- Los productos se cargarán aquí -->
            </div>

            <!-- ESTADOS: Carga y Vacío -->
            <div class="loading-state" id="loading">
                <div class="spinner"></div>
                <p>Buscando productos increíbles...</p>
            </div>

            <div class="empty-state" id="empty-state" style="display: none;">
                <i class="fas fa-search"></i>
                <h3>No encontramos productos</h3>
                <p>Intenta con otros filtros o búsqueda</p>
                <button class="reset-btn" onclick="resetFilters()">
                    <i class="fas fa-redo"></i> Limpiar filtros
                </button>
            </div>
        </div>
    </div>

    <!-- CALL TO ACTION VENDEDOR - Psicología Zeigarnik -->
    <div class="seller-cta">
        <div class="container">
            <div class="cta-content">
                <div class="cta-text">
                    <h3><i class="fas fa-suitcase-rolling"></i> ¿Viajas pronto?</h3>
                    <p>Gana dinero trayendo productos que otros necesitan</p>
                </div>
                <a href="shop-manage-products.php" class="cta-btn">
                    <i class="fas fa-plus-circle"></i> Vender productos
                </a>
            </div>
        </div>
    </div>

    <!-- CARRITO FLOTANTE - Efecto Fitts: grande y accesible -->
    <button class="cart-floating" onclick="openCart()" aria-label="Abrir carrito">
        <i class="fas fa-shopping-cart"></i>
        <span class="cart-badge" id="cart-count">0</span>
        <span class="cart-label">Carrito</span>
    </button>

    <!-- Footer dinámico -->
    <?php if (file_exists('../footer.php')): ?>
        <?php include '../footer.php'; ?>
    <?php endif; ?>

    <!-- SCRIPTS -->
    <script>
        // ====================================
        // ESTADO GLOBAL - Declarar ANTES de cargar cart.js
        // ====================================
        let allProducts = [];
        let filteredProducts = [];
        // NO declarar cart aquí, ya está en cart.js
        let currentFilters = {
            search: '',
            category: 'all',
            origin: '',
            destination: '',
            currency: ''
        };
    </script>
    
    <!-- Cargar cart.js DESPUÉS de declarar allProducts -->
    <script src="cart.js"></script>
    
    <script>
        // ====================================
        // INICIALIZACIÓN
        // ====================================
        document.addEventListener('DOMContentLoaded', function() {
            loadProducts();
            initSearchSync();
            initStickySearch();
        });

        // ====================================
        // BUSCADOR STICKY
        // ====================================
        function initStickySearch() {
            const stickyWrapper = document.getElementById('search-sticky-wrapper');
            const heroSection = document.querySelector('.hero-section');

            window.addEventListener('scroll', function() {
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                const heroHeight = heroSection.offsetHeight;

                if (scrollTop > heroHeight - 100) {
                    stickyWrapper.classList.add('visible');
                } else {
                    stickyWrapper.classList.remove('visible');
                }
            });
        }

        // ====================================
        // SINCRONIZAR AMBOS BUSCADORES
        // ====================================
        function initSearchSync() {
            const heroSearch = document.getElementById('hero-search');
            const stickySearch = document.getElementById('sticky-search');
            
            let searchTimeout;
            
            heroSearch.addEventListener('input', function(e) {
                stickySearch.value = e.target.value;
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    performSearch(e.target.value);
                }, 500);
            });
            
            stickySearch.addEventListener('input', function(e) {
                heroSearch.value = e.target.value;
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    performSearch(e.target.value);
                }, 500);
            });

            heroSearch.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(searchTimeout);
                    quickSearch();
                }
            });

            stickySearch.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(searchTimeout);
                    quickSearch();
                }
            });
        }

        function performSearch(searchValue) {
            currentFilters.search = searchValue;
            applyAllFilters();
            
            if (searchValue.trim() !== '') {
                highlightSearchTerm(searchValue);
            } else {
                document.getElementById('search-term-display').style.display = 'none';
            }
        }

        function quickSearch() {
            const heroSearch = document.getElementById('hero-search');
            const stickySearch = document.getElementById('sticky-search');
            const searchValue = heroSearch.value || stickySearch.value;
            
            currentFilters.search = searchValue;
            applyAllFilters();
            
            if (searchValue.trim() !== '') {
                scrollToResults();
                highlightSearchTerm(searchValue);
                
                [heroSearch, stickySearch].forEach(input => {
                    input.style.borderColor = '#41ba0d';
                    input.style.boxShadow = '0 0 0 3px rgba(65, 186, 13, 0.2)';
                });
                
                setTimeout(() => {
                    [heroSearch, stickySearch].forEach(input => {
                        input.style.borderColor = '';
                        input.style.boxShadow = '';
                    });
                }, 1000);
            }
        }

        function scrollToResults() {
            const resultsSection = document.getElementById('products-section');
            const resultsBar = document.getElementById('results-bar');
            const stickyHeight = 90;
            const targetPosition = resultsSection.offsetTop - stickyHeight;
            
            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth'
            });

            setTimeout(() => {
                resultsBar.classList.add('pulse-animation');
                setTimeout(() => {
                    resultsBar.classList.remove('pulse-animation');
                }, 1000);
            }, 300);
        }

        function highlightSearchTerm(searchTerm) {
            const searchDisplay = document.getElementById('search-term-display');
            
            if (searchTerm.trim() !== '') {
                searchDisplay.innerHTML = `para "<strong>${searchTerm}</strong>"`;
                searchDisplay.style.display = 'inline';
            } else {
                searchDisplay.style.display = 'none';
            }
        }

        // ====================================
        // MODAL DE FILTROS
        // ====================================
        function openFiltersModal() {
            document.getElementById('filters-modal-overlay').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeFiltersModal() {
            document.getElementById('filters-modal-overlay').classList.remove('active');
            document.body.style.overflow = '';
        }

        function selectCategory(button, category) {
            document.querySelectorAll('.filter-option').forEach(opt => {
                opt.classList.remove('active');
            });
            button.classList.add('active');
            currentFilters.category = category;
        }

        function applyFiltersModal() {
            currentFilters.origin = document.getElementById('origin-modal').value;
            currentFilters.destination = document.getElementById('destination-modal').value;
            currentFilters.currency = document.getElementById('currency-modal').value;
            
            applyAllFilters();
            updateActiveFiltersCount();
            closeFiltersModal();
            scrollToResults();
            
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Filtros aplicados',
                timer: 1500,
                showConfirmButton: false
            });
        }

        function resetFiltersModal() {
            currentFilters.category = 'all';
            currentFilters.origin = '';
            currentFilters.destination = '';
            currentFilters.currency = '';
            
            document.querySelectorAll('.filter-option').forEach(opt => {
                opt.classList.toggle('active', opt.dataset.category === 'all');
            });
            
            document.getElementById('origin-modal').value = '';
            document.getElementById('destination-modal').value = '';
            document.getElementById('currency-modal').value = '';
            
            applyAllFilters();
            updateActiveFiltersCount();
            closeFiltersModal();
            
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: 'Filtros eliminados',
                timer: 1500,
                showConfirmButton: false
            });
        }

        function updateActiveFiltersCount() {
            let count = 0;
            
            if (currentFilters.category !== 'all') count++;
            if (currentFilters.origin) count++;
            if (currentFilters.destination) count++;
            if (currentFilters.currency) count++;
            
            const badge = document.getElementById('active-filters-count');
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }

        // ====================================
        // CARGAR PRODUCTOS
        // ====================================
        async function loadProducts() {
            showLoading(true);
            
            try {
                const response = await fetch('shop-actions.php?action=get_products');
                const data = await response.json();
                
                if (data.success) {
                    allProducts = data.products;
                    filteredProducts = [...allProducts];
                    renderProducts();
                    updateProductCount();
                } else {
                    throw new Error(data.error || 'Error desconocido');
                }
            } catch (error) {
                console.error('Error cargando productos:', error);
                showError('No pudimos cargar los productos. Por favor, recarga la página.');
                showEmptyState(true);
            } finally {
                showLoading(false);
            }
        }

        // ====================================
        // RENDERIZAR PRODUCTOS
        // ====================================
        function renderProducts() {
            const grid = document.getElementById('products-grid');
            
            if (filteredProducts.length === 0) {
                showEmptyState(true);
                grid.style.display = 'none';
                return;
            }

            showEmptyState(false);
            grid.style.display = 'grid';
            
            grid.innerHTML = filteredProducts.map(product => `
                <article class="product-card">
                    <div class="product-image-wrapper">
                        <img src="${product.primary_image || 'https://via.placeholder.com/300x240?text=Sin+Imagen'}" 
                             alt="${product.name}"
                             loading="lazy"
                             class="product-image">
                        
                        <div class="product-badges">
                            ${product.trip_info ? `
                                <span class="badge badge-trip">
                                    <i class="fas fa-plane"></i> ${product.trip_info}
                                </span>
                            ` : ''}
                        </div>

                        <div class="product-price-overlay">
                            ${formatPrice(product.price, product.currency)}
                        </div>
                    </div>

                    <div class="product-content">
                        <h3 class="product-name">${product.name}</h3>
                        <p class="product-description">${truncateText(product.description, 80)}</p>

                        <div class="product-seller">
                            <img src="../mostrar_imagen.php?id=${product.seller_id}" 
                                 alt="${product.seller_name}"
                                 class="seller-avatar"
                                 onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(product.seller_name)}&background=41ba0d&color=fff'">
                            <div class="seller-details">
                                <strong class="seller-name">${product.seller_name}</strong>
                                <div class="seller-rating">
                                    ${generateStars(product.seller_rating)}
                                    <span class="rating-number">${product.seller_rating.toFixed(1)}</span>
                                </div>
                            </div>
                        </div>

                        <div class="product-stock ${product.stock < 5 ? 'low-stock' : ''}">
                            <i class="fas fa-box"></i>
                            ${product.stock < 5 
                                ? `<strong>¡Solo ${product.stock}!</strong> quedan` 
                                : `${product.stock} disponibles`
                            }
                        </div>

                        <button class="add-cart-btn" onclick="addToCart(${product.id})" data-product-id="${product.id}">
                            <i class="fas fa-cart-plus"></i>
                            <span>Añadir al carrito</span>
                        </button>
                    </div>
                </article>
            `).join('');
        }

        // ====================================
        // APLICAR FILTROS
        // ====================================
        function applyAllFilters() {
            filteredProducts = allProducts.filter(product => {
                if (currentFilters.category !== 'all' && product.category !== currentFilters.category) {
                    return false;
                }
                
                if (currentFilters.search && !product.name.toLowerCase().includes(currentFilters.search.toLowerCase()) 
                    && !product.description.toLowerCase().includes(currentFilters.search.toLowerCase())) {
                    return false;
                }
                
                if (currentFilters.origin && product.origin !== currentFilters.origin) {
                    return false;
                }
                
                if (currentFilters.destination && product.destination !== currentFilters.destination) {
                    return false;
                }
                
                if (currentFilters.currency && product.currency !== currentFilters.currency) {
                    return false;
                }
                
                return true;
            });
            
            renderProducts();
            updateProductCount();
        }

        function resetFilters() {
            currentFilters = {
                search: '',
                category: 'all',
                origin: '',
                destination: '',
                currency: ''
            };
            
            document.getElementById('hero-search').value = '';
            document.getElementById('sticky-search').value = '';
            
            document.querySelectorAll('.filter-option').forEach(opt => {
                opt.classList.toggle('active', opt.dataset.category === 'all');
            });
            
            document.getElementById('origin-modal').value = '';
            document.getElementById('destination-modal').value = '';
            document.getElementById('currency-modal').value = '';
            document.getElementById('search-term-display').style.display = 'none';
            
            applyAllFilters();
            updateActiveFiltersCount();
        }

        // ====================================
        // ORDENAMIENTO
        // ====================================
        function sortProducts(sortBy) {
            switch(sortBy) {
                case 'price-low':
                    filteredProducts.sort((a, b) => a.price - b.price);
                    break;
                case 'price-high':
                    filteredProducts.sort((a, b) => b.price - a.price);
                    break;
                case 'rating':
                    filteredProducts.sort((a, b) => b.seller_rating - a.seller_rating);
                    break;
                default:
                    applyAllFilters();
                    return;
            }
            
            renderProducts();
        }

        // ====================================
        // UTILIDADES
        // ====================================
        function formatPrice(amount, currency) {
            const symbols = {
                'EUR': '€', 'USD': '$', 'BOB': 'Bs.', 'BRL': 'R$',
                'ARS': '$', 'VES': 'Bs.', 'COP': '$', 'MXN': '$',
                'NIO': 'C$', 'CUP': '$MN', 'PEN': 'S/'
            };
            
            const symbol = symbols[currency] || currency;
            const price = parseFloat(amount).toFixed(2);
            return `<span class="currency-symbol">${symbol}</span>${price}`;
        }

        function truncateText(text, maxLength) {
            if (text.length <= maxLength) return text;
            return text.substring(0, maxLength) + '...';
        }

        function generateStars(rating) {
            const fullStars = Math.floor(rating);
            let stars = '';
            for (let i = 0; i < 5; i++) {
                stars += i < fullStars 
                    ? '<i class="fas fa-star"></i>' 
                    : '<i class="far fa-star"></i>';
            }
            return stars;
        }

        function updateProductCount() {
            document.getElementById('products-count').textContent = filteredProducts.length;
        }

        function showLoading(show) {
            document.getElementById('loading').style.display = show ? 'flex' : 'none';
        }

        function showEmptyState(show) {
            document.getElementById('empty-state').style.display = show ? 'flex' : 'none';
        }

        function showError(message) {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: message,
                confirmButtonColor: '#41ba0d'
            });
        }
    </script>
</body>
</html>