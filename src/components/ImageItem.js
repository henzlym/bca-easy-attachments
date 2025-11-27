/**
 * Image Item Component
 *
 * Displays individual image with download options.
 *
 * @since 1.0.0
 * @package EasyAttachments
 */

import { Button, Dropdown, MenuGroup, MenuItem } from "@wordpress/components";
import { useState } from "@wordpress/element";
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
	const [showSizeMenu, setShowSizeMenu] = useState(false);

	const containerClass = [
		"easy-attachments-sidebar__photo",
		isDownloading && "is-downloading",
		isDownloaded && "is-downloaded",
	]
		.filter(Boolean)
		.join(" ");

	// Available image sizes from Unsplash
	const imageSizes = [
		{ label: "Raw (Original)", value: "raw", size: "Original quality" },
		{ label: "Full", value: "full", size: "~6000px" },
		{ label: "Regular", value: "regular", size: "~1080px", recommended: true },
		{ label: "Small", value: "small", size: "~400px" },
		{ label: "Thumb", value: "thumb", size: "~200px" },
	];

	const handleDownloadWithSize = (action, size = "full") => {
		onDownload(photo, action, size);
		setShowSizeMenu(false);
	};

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
				<Dropdown
					className="easy-attachments-sidebar__size-dropdown"
					position="top center"
					renderToggle={({ isOpen, onToggle }) => (
						<Button
							icon={<InsertIcon />}
							label="Insert into post"
							onClick={onToggle}
							disabled={isDownloading}
							aria-expanded={isOpen}
						/>
					)}
					renderContent={({ onClose }) => (
						<MenuGroup label="Select image size">
							{imageSizes.map((size) => (
								<MenuItem
									key={size.value}
									onClick={() => {
										handleDownloadWithSize("in-post", size.value);
										onClose();
									}}
								>
									<div className="size-menu-item">
										<strong>
											{size.label}
											{size.recommended && (
												<span className="recommended-badge">
													{" "}
													• Recommended
												</span>
											)}
										</strong>
										<span className="size-info">{size.size}</span>
									</div>
								</MenuItem>
							))}
						</MenuGroup>
					)}
				/>

				<Dropdown
					className="easy-attachments-sidebar__size-dropdown"
					position="top center"
					renderToggle={({ isOpen, onToggle }) => (
						<Button
							icon={<ImageIcon />}
							label="Set as featured image"
							onClick={onToggle}
							disabled={isDownloading}
							aria-expanded={isOpen}
						/>
					)}
					renderContent={({ onClose }) => (
						<MenuGroup label="Select image size">
							{imageSizes.map((size) => (
								<MenuItem
									key={size.value}
									onClick={() => {
										handleDownloadWithSize("featured-image", size.value);
										onClose();
									}}
								>
									<div className="size-menu-item">
										<strong>
											{size.label}
											{size.recommended && (
												<span className="recommended-badge">
													{" "}
													• Recommended
												</span>
											)}
										</strong>
										<span className="size-info">{size.size}</span>
									</div>
								</MenuItem>
							))}
						</MenuGroup>
					)}
				/>

				<Dropdown
					className="easy-attachments-sidebar__size-dropdown"
					position="top center"
					renderToggle={({ isOpen, onToggle }) => (
						<Button
							icon={<DownloadIcon />}
							label="Download to media library"
							onClick={onToggle}
							disabled={isDownloading}
							aria-expanded={isOpen}
						/>
					)}
					renderContent={({ onClose }) => (
						<MenuGroup label="Select image size">
							{imageSizes.map((size) => (
								<MenuItem
									key={size.value}
									onClick={() => {
										handleDownloadWithSize("media-library", size.value);
										onClose();
									}}
								>
									<div className="size-menu-item">
										<strong>
											{size.label}
											{size.recommended && (
												<span className="recommended-badge">
													{" "}
													• Recommended
												</span>
											)}
										</strong>
										<span className="size-info">{size.size}</span>
									</div>
								</MenuItem>
							))}
						</MenuGroup>
					)}
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
