# Nueva Arquitectura v7.0 - En Desarrollo

## Estado Actual
🚧 **Work in Progress** - Sistema en refactorización completa

## Estructura
```
admin/
├── actions/          # Módulos de acción (qué modificar)
├── conditions/       # Módulos de condición (filtros/ámbitos)
├── managers/         # Gestores de carga automática
└── metaboxes/        # Clases base y metaboxes
```

## Próximos Pasos
1. ✅ Reorganizar estructura
2. 🚧 Crear class-action-base.php con sistema de contextos
3. ⏳ Actualizar managers con auto-discovery
4. ⏳ Migrar módulos existentes
5. ⏳ Implementar sistema de guardado individual
6. ⏳ Crear plantillas CSS/JS compartidas

## Características Nuevas v7.0
- Sistema de contextos universal (products, posts, pages, shortcodes)
- Generación automática de código ejecutable
- UI modular con guardar/descartar individual
- Copiar opciones entre reglas
- Shortcodes dinámicos

