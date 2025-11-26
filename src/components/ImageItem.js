/**
 * Image Item Component
 *
 * Displays individual image with download options.
 *
 * @since 1.0.0
 * @package EasyAttachments
 */

import { Button } from "@wordpress/components";
import { DownloadIcon, ImageIcon, InsertIcon } from "../icons";

/**
 * ImageItem component props.
 *
 * @typedef {Object} ImageItemProps
 * @property {Object}   photo         The photo object from Unsplash.
 * @property {boolean}  isDownloading Whether this image is currently downloading.
 * @property {boolean}  isDownloaded  Whether this image was just downloaded.
 * @property {Function} onDownload    Callback function for download action.
 */

/**
 * ImageItem component.
 *
 * @param {ImageItemProps} props Component props.
 * @return {JSX.Element} The image item component.
 */
function ImageItem({ photo, isDownloading, isDownloaded, onDownload }) {
	const { id, urls, alt_description, description, user } = photo;

	const containerClass = [
		"easy-attachments-sidebar__photo",
		isDownloading && "is-downloading",
		isDownloaded && "is-downloaded",
	]
		.filter(Boolean)
		.join(" ");

	return (
		<div id={`photo-${id}`} className={containerClass}>
			<div className="easy-attachments-sidebar__photo-user">
				<img src={user.profile_image.small} alt={user.name} />
				<span>{user.name}</span>
			</div>

			<img
				src={urls.regular}
				alt={alt_description || description}
				loading="lazy"
			/>

			<div className="easy-attachments-sidebar__actions">
				<Button
					icon={<InsertIcon />}
					label="Insert into post"
					onClick={() => onDownload(photo, "in-post")}
					disabled={isDownloading}
				/>
				<Button
					icon={<ImageIcon />}
					label="Set as featured image"
					onClick={() => onDownload(photo, "featured-image")}
					disabled={isDownloading}
				/>
				<Button
					icon={<DownloadIcon />}
					label="Download to media library"
					onClick={() => onDownload(photo, "media-library")}
					disabled={isDownloading}
				/>
			</div>

			{(isDownloading || isDownloaded) && (
				<div className="easy-attachments-sidebar__overlay">
					<span>{isDownloading ? "Downloading..." : "Downloaded!"}</span>
					{isDownloading && (
						<div className="easy-attachments-sidebar__progress" />
					)}
				</div>
			)}
		</div>
	);
}

export default ImageItem;
