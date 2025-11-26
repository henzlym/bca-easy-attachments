import { useEffect, useState } from "@wordpress/element";

/**
 * Custom hook for fetching data from an API.
 *
 * @param {string} url     The URL to fetch from.
 * @param {Object} options Fetch options (headers, etc.).
 * @return {Object} Object containing data, error, and loading state.
 */
function useFetch(url, options = {}) {
	const [data, setData] = useState(null);
	const [error, setError] = useState(null);
	const [isLoading, setIsLoading] = useState(true);

	useEffect(() => {
		let isMounted = true;

		// Reset states when URL changes.
		setIsLoading(true);
		setError(null);

		fetch(url, options)
			.then((res) => {
				if (!res.ok) {
					throw new Error(`HTTP error! status: ${res.status}`);
				}
				return res.json();
			})
			.then((responseData) => {
				if (!isMounted) return;
				setData(responseData);
				setError(null);
			})
			.catch((err) => {
				if (!isMounted) return;
				setError(err);
				setData(null);
			})
			.finally(() => {
				if (isMounted) {
					setIsLoading(false);
				}
			});

		return () => {
			isMounted = false;
		};
	}, [url]); // Only depend on URL, not options

	return { data, error, isLoading };
}

export default useFetch;
