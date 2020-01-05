=== Auto Smart Thumbnails ===

Plugin creates thumbnails on demand with face detection. Remove unused thumbnails and downsizes images. Free up server storage.

== Description ==

I. Face detetion

WordPress (WP) plugin/themes crop images per fixed position (top, center, bottom x left, center, right). This often generates thumbnails with faces being cut. This plugin (Auto Smart Thumbnails, AST) employs face detection to keep the face. 

II. Downsize images

There are many ways to backup/store your images. Your webhost server is the last place for that though. Essentially, your web server serves one purpose and one purpose only: a fast website. So making it small and agile is critical in both user experience and website maintenance. 

Media files (pdf, movie and images) are usually the biggest space eaters. Here are some practice tips related to images:
1. Use jpg to store images. No png except for the logo images. 
2. Downsize your images to about (1920x1080, full high definition, FHD). 
3. Get rid of those unsed thumbnails. 

AST does 2 and 3. It helped to trim my website from 24G to 9G. 

AST downsizes big images in a smart way. It does so by a factor of an integer, e.g. 2, 3, 4 etc, so that the result image looks as sharp as the original in a webpage. E.g. an image of (5184x3456) is downsized by 3 to (1728x1152) and its file size is down from 4.9M to 239K. Conventional tools downsize it by 3.2 (=3456/1080) to (1687x1080, short side exact FHD). Blurring happens because of the pixels fractioned. 

For images smaller than 3840x2160, which can't even be downsized by a factor of 2, they will be compressed (at WP default quality of 82%. Though the document says the default quality is 90%, in code it's 82%.)

III. Cleanup thumbnails

Some WP themes generate many, sometimes 10s of, custom sized thumbnails when an image is uploaded. These thumbnails may never be used yet take up your precious server storage space. AST helps remove these unused thumbnails and stop them from being generated when an image is uploaded. A thumbnail is generated only when it is requested. The newly generated thumbnail is then stored for later use.

== Notes ==
1. AST is based on 'Optimize Images Resizing' by OriginalEXE. 

2. Face detection algorithm is by Maurice Svay (php-facedetection by mauricesvay at github.com). It returns only the first face candidate detectd. It mostly does the job and and it's a little faster then another implementation PHP-FaceDetector by Felix Koch. When it fails to detect face(s), the cropping will done by the system.

2.1 The module is designed with extensibility that other plugin can do face detection, e.g. faster or with more accuracy, and feeds the dection result with the meta data of an image. AST can pick up the data to do cropping. This is done by adding a new field 'focal_area' in the meta data, as below:

	Array (
		[width] => 512
		[height] => 512
		[file] => 2019/04/sample-image-file.jpg
		[sizes] => Array ()
		[focal_area] = (
			[x] => 100
			[y] => 123
			[w] => 58
            [h] => 58
			[faces] => Array (
				[tharavaad-svay] => Array (
					[0] => Array (
						[x] => 100
						[y] => 123
						[w] => 58
					)
				)
				[koch] => Array (
					[0] => Array (
						[x] => 100
						[y] => 123
						[w] => 58
					)
					[1] => Array (
						...
					)
				)
			)
		)
    )

    The focal_area is defined by the position (x,y) and width and height. External plugin can store the detection result with these 4 parameters. AST can pick them up for cropping.

2.2 The 'focal_area' can be non-face objects that users want to focus on. Within it, the optional 'faces' array defines faces detected and the algorithm used.

3. To make it simple, some assumption and number decisions below:

3.1 To resave png images in jpg will save a lot space. But it needs to mess up with the WP database, which I stayed away. 

3.2 An image is downsized only when its short side > 2x1080. Otherwise it's re-compressed when its size >128k bytes. The new jpg file replaces the original only when it's 25k bytes smaller.

3.3 When a downsizing happens, the original is saved in uploads/ast-backup. The year/month structure is preserved. To save the server storage space, it's recommended to ftp download it and delete it from the server. 

4. I didn't get time to handle the localization language files yet. 

5. Please let me know how it works for you, or any improvement suggestions or feedback. 

== Installation ==

In WP 'Add Plugins', seach for 'auto-smart-thumbnails' and 'Install Now', and activate it. 

== Frequently Asked Questions ==

**I just installed the plugin. Is there anything else I need to do?**

No. AST works silently in the background. If your site has many existing thumbnails, you can remove them manually by 'Tools -> Auto Smart Thumbnails' or 'Installed Plugins -> Auto Smart Thumbnails -> Remove Unused Thumbnails'. 

**Some image sizes are not cleaned, why?**

AST doesn't remove the default thumbanil which is defined by WP and WP uses it frequently.

**How do I know which files the plugin cleaned up?**

A list of removed files is available after a cleanup, When a cleanup is done, a message will show how many images is removed. Click on the number to show the list.

**Are there any drawbacks to using AST?**

Not as I know. Your WP website will continue working as is. But your uploads folder will be lighter. It helps save your server storage and makes backup easier and faster. 

**I run into problems. What can I do?**
You can turn on debug and get the logged debug info. Email it to me or post it at the support forum. Check 'Log debug info for troubleshooting' in 'Settings -> Auto Smart Thumbnails'. After that, you will see a button 'Get Debug Log' in 'Tools -> Auto Smart Thumbnails'. The 'Get Debug Log' button has a minor glitch. I'd like it to show up when there is info logged after a cleanup is done. But it doesn't show. Appreciate if someone can help!

**I still see a lot of big png images, what can I do?**
To downsize or re-compress image png files to jpg needs to change database. To keep the data and files in sync needs a lot messy code. So I decided not to do it. 

My suggestion is to use XnViewMP to convert the png to jpg before uploading to your media library. XnViewMP can do batch coversion and amazing color correction. It got only one drawback that it can't decide the target size intellgiently as AST does. You need to manually calculate the size to downsize to and different images may need seperate calculations. 

**Have you checked smartcrop plugins currently available?**
At the moment, there are two plugins: 'SmartCrop' by late Alex Mills (Viper007Bond) and 'WP SmartCrop' by 'Burlington Bytes'. The later provices a tool for users to set a focal point manually. Alex's SmartCrop plugin uses Smart Cropping Class/algorithm developed by Greg Schoppe, which is based on color difference and image entropy and puts the focus of the image at or close to the photograph rule of thirds line. It doesn't do face detection. AST is the first WordPress plugin that does smart crop with face detection.


== Screenshots ==

1. Admin UI added by the plugin.
2. Log page after a cleanup.
3. Thumbnails generated by WP default. 
4. Thumbnails generated by SmartCrop. 
5. Thumbnails generated by AST. 
6. Thumbnails before cleanup. 
7. Thumbnails after cleanup. 

== Upgrade Notice ==
none

== Changelog ==

= 1.1.0 =
1. Add face detection.
2. Add debug info log for troubleshooting.

= 1.0.0 =
* Initial version. Based on 'Optimize Images Resizing' by OriginalEXE. Major issues fixed: 
1. During pagination, WP_Query returns corrupted result (duplicate and missing post IDs).
2. Nothing happends when button 'Start new cleanup' is clicked.
3. Sizes in meta data and thumbnail files out of sync. 
