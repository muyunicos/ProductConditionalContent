# Sistema Modular de Reglas de Contenido

## 📋 Descripción

Sistema modular escalable para gestionar características de productos WooCommerce mediante reglas condicionales.

## 🏗️ Arquitectura

### Componentes Principales

1. **GDM_Module_Base** (`class-module-base.php`)
   - Clase abstracta base para todos los módulos
   - Proporciona métodos helper reutilizables
   - Gestión automática de metaboxes y guardado
   - Validación de activación de módulos

2. **GDM_Module_Manager** (`class-module-manager.php`)
   - Gestor centralizado de módulos
   - Registro y inicialización automática
   - Sistema de hooks para extensibilidad
   - Control de módulos habilitados/deshabilitados

### Módulos Disponibles

#### Fase 1 (Implementado)
- ✅ **Descripción** - Gestión de descripciones largas y cortas

#### Fase 2+ (Preparado)
- ⏳ **Precio** - Modificación dinámica de precios
- ⏳ **Título** - Edición condicional de títulos
- ⏳ **Imagen** - Gestión de imágenes destacadas
- ⏳ **SKU** - Modificación de SKU
- ⏳ **Stock** - Control de estado de stock

## 🔌 Uso para Desarrolladores

### Registrar un Módulo Personalizado

```php
add_action('gdm_register_modules', function($manager) {
    $manager->register_module('mi_modulo', [
        'class' => 'Mi_Modulo_Personalizado',
        'label' => __('Mi Módulo', 'mi-textdomain'),
        'icon' => '🎨',
        'file' => plugin_dir_path(__FILE__) . 'class-mi-modulo.php',
        'enabled' => true,
    ]);
});