# Easy Attachments Plugin Optimization Plan

## Files to Remove
1. `src/sidebar.js` - Duplicate of v2 functionality
2. `src/sidebar/Sidebar.js` - Old class component (replaced by v2 functional)
3. `src/common.css` - Empty
4. `src/common.scss` - Unused variables
5. `src/components/search-field.js` - Redundant with search.js
6. `src/components/search-results.js` - If exists and unused

## Optimizations to Apply
1. Consolidate v2/sidebar.js as main Sidebar component
2. Convert Image component from class to functional
3. Add proper JSDoc comments throughout
4. Follow WordPress coding standards
5. Use modern React hooks (useCallback, useMemo)
6. Consolidate localized scripts
7. Remove debug logging from production
8. Add proper error boundaries
9. Improve component organization
10. Add prop-types validation

## Structure After Optimization
```
bca-easy-attachments/
├── bca-easy-attachments.php (main plugin file)
├── src/
│   ├── index.js (entry point)
│   ├── init.php (REST API + enqueue)
│   ├── components/
│   │   ├── Sidebar.js (main component)
│   │   ├── Image.js (optimized)
│   │   └── SearchControl.js (consolidated search)
│   ├── hooks/
│   │   └── useFetch.js
│   ├── icons/
│   │   └── (all SVG icons)
│   └── styles/
│       └── sidebar.scss
└── build/ (webpack output)
```
