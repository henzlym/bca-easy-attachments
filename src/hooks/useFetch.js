import { useEffect, useMemo, useState } from '@wordpress/element';
function useFetch( url, options = {}, method = 'GET') {
    const [ data, setData ] = useState(null)
    const [ error, setError ] = useState(null)
    const [ isLoading, setIsLoading ] = useState(false)
    
    useEffect(() => {
        
        let isMounted = true
        
        fetch(url,options)
            .then( res => res.json() )
            .then( data => {
                if (!isMounted) return;
                setData(data);
                setError(null);
            } )
            .catch( error => {
                if (!isMounted) return;
                setError(error);
                setData(null);
            })
            .finally( () => isMounted && setIsLoading(false) );
            
        return () => ( isMounted = false );
        
    }, [url, options]);
    
    return { data, error, isLoading }
}

export default useFetch;