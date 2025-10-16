# Nueva Arquitectura v7.0

## Estado Actual
✅ **Core Infrastructure Complete** - Listo para migrar módulos restantes

## Estructura
```
admin/
├── actions/          # Módulos de acción (qué modificar)
│   ├── class-action-price.php (✅ REFACTORIZADO v7.0)
│   ├── class-action-description.php (⏳ Pendiente migración)
│   ├── class-action-gallery.php (⏳ Pendiente migración)
│   ├── class-action-featured.php (⏳ Pendiente migración)
│   └── class-action-variants.php (⏳ Pendiente migración)
├── conditions/       # Módulos de condición (filtros/ámbitos)
│   ├── class-condition-products.php (✅ Compatible v7.0)
│   ├── class-condition-categories.php (✅ Compatible v7.0)
│   ├── class-condition-tags.php (✅ Compatible v7.0)
│   ├── class-condition-attributes.php (✅ Compatible v7.0)
│   ├── class-condition-price.php (✅ Compatible v7.0)
│   ├── class-condition-title.php (✅ Compatible v7.0)
│   └── class-condition-product-types.php (✅ Compatible v7.0)
├── managers/         # Gestores de carga automática
│   ├── class-action-manager.php (✅ v7.0 con auto-discovery)
│   └── class-condition-manager.php (✅ Compatible v7.0)
└── metaboxes/        # Clases base y metaboxes
    ├── class-action-base.php (✅ NUEVO v7.0)
    ├── class-condition-base.php (✅ ACTUALIZADO v7.0)
    └── class-rules-config-metabox.php (⏳ Pendiente actualización)
```

## Completado ✅
1. ✅ Reorganizar estructura de directorios
2. ✅ Crear class-action-base.php con sistema multi-contexto
3. ✅ Actualizar class-condition-base.php con soporte de contextos
4. ✅ Crear class-action-manager.php con auto-discovery
5. ✅ Refactorizar price module como ejemplo de referencia
6. ✅ Implementar sistema de generación de código ejecutable

## Próximos Pasos ⏳
1. Migrar módulos restantes a nueva arquitectura:
   - description (texto enriquecido)
   - gallery (imágenes)
   - featured (destacado)
   - variants (variantes condicionales)
2. Actualizar metabox principal (class-rules-config-metabox.php)
3. Crear/actualizar CSS/JS compartido para actions
4. Implementar sistema de ejecución de reglas en frontend
5. Testing completo del sistema

## Características v7.0
- ✅ **Sistema de contextos universal**: products, posts, pages, shortcode, wc_cart, wc_checkout
- ✅ **Auto-discovery de módulos**: Escaneo automático de class-action-*.php
- ✅ **Generación automática de código ejecutable**: Cada action genera su propio código
- ✅ **Storage centralizado**: _gdm_actions_config con estructura unificada
- ⏳ **UI modular**: Guardar/descartar individual por acción (pendiente JS)
- ⏳ **Copiar opciones entre reglas**: Sistema de exportación/importación
- ⏳ **Shortcodes dinámicos**: [gdm_content rule="123" context="shortcode"]

## Guía de Migración de Módulos

Para migrar un módulo existente a v7.0:

### 1. Cambiar clase base
```php
// ANTES:
class GDM_Module_Description extends GDM_Module_Base

// DESPUÉS:
class GDM_Action_Description extends GDM_Action_Base
```

### 2. Actualizar propiedades
```php
// ANTES:
protected $module_id = 'descripcion';
protected $module_name = 'Descripción';
protected $module_icon = '📝';

// DESPUÉS:
protected $action_id = 'description';
protected $action_name = 'Modificador de Descripción';
protected $action_icon = '📝';
protected $action_description = 'Permite reemplazar o modificar la descripción del producto';
protected $supported_contexts = ['products', 'posts'];
```

### 3. Implementar métodos abstractos requeridos
```php
// 1. Opciones por defecto
protected function get_default_options() {
    return ['option1' => 'value1'];
}

// 2. Renderizar UI
protected function render_options($rule_id, $options) {
    // HTML con campos: gdm_actions[{action_id}][options][field_name]
}

// 3. Sanitizar datos del POST
protected function sanitize_options($post_data) {
    return [
        'option1' => $this->sanitize_text($post_data['options']['option1']),
    ];
}

// 4. Generar código ejecutable
protected function generate_execution_code($options) {
    return "\$result = do_something(\$object_id, '{$options['option1']}'); return \$result;";
}
```

### 4. Estructura de campos en formulario
```html
<!-- ANTES: -->
<input name="gdm_descripcion_texto" />

<!-- DESPUÉS: -->
<input name="gdm_actions[description][options][texto]" />
```

### 5. Storage de datos
```php
// ANTES: Múltiples post_meta
_gdm_descripcion_texto
_gdm_descripcion_tipo
_gdm_descripcion_formato

// DESPUÉS: Un solo post_meta unificado
_gdm_actions_config = [
    'description' => [
        'enabled' => true,
        'contexts' => ['products'],
        'options' => [
            'texto' => 'Lorem ipsum',
            'tipo' => 'replace',
            'formato' => 'html'
        ],
        'code' => 'PHP code string...',
        'last_updated' => '2025-10-16 18:00:00'
    ]
]
```

## Testing v7.0

Para testear el sistema actual:

1. Acceder a editar una regla: `/wp-admin/post.php?post=ID&action=edit`
2. Agregar parámetro `?debug_actions=1` para ver debug del action manager
3. Verificar que price module se autodescubre y carga correctamente
4. Las conditions siguen funcionando con la arquitectura anterior (retrocompatibilidad)

## Documentación Técnica

### Auto-Discovery
El Action Manager escanea automáticamente `includes/admin/actions/` buscando archivos con patrón `class-action-*.php` y los registra automáticamente. El ID se extrae del nombre del archivo.

Ejemplo:
- `class-action-price.php` → action_id: `price`, clase: `GDM_Action_Price`
- `class-action-description.php` → action_id: `description`, clase: `GDM_Action_Description`

### Execution Flow
1. Usuario guarda regla
2. Action Manager llama `save()` en cada action habilitada
3. Action sanitiza opciones mediante `sanitize_options()`
4. Action genera código ejecutable mediante `generate_execution_code()`
5. Todo se guarda en `_gdm_actions_config`
6. En frontend, Rules Engine evalúa conditions
7. Si pasa, ejecuta el código generado con `eval()` en contexto seguro

