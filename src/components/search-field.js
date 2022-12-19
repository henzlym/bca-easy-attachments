import { SearchControl } from '@wordpress/components';
import { useState } from '@wordpress/element';

function SearchField( { value, onChange, onKeyDown } ) {
    return (
        <SearchControl
            value={ value }
            onChange={ onChange }
            onKeyDown ={ onKeyDown }
        />
    );
}

export default SearchField;