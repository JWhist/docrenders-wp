import { chromium } from 'playwright';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname( fileURLToPath( import.meta.url ) );
const ASSETS    = path.join( __dirname, 'assets' );
const WP        = 'http://localhost:8888';
const USER      = 'admin';
const PASS      = 'password';
const WIDTH     = 1200;
const HEIGHT    = 900;

async function login( page ) {
	await page.goto( `${ WP }/wp-login.php` );
	await page.fill( '#user_login', USER );
	await page.fill( '#user_pass', PASS );
	await page.click( '#wp-submit' );
	await page.waitForURL( `${ WP }/wp-admin/**` );
}

( async () => {
	const browser = await chromium.launch();
	const ctx     = await browser.newContext( { viewport: { width: WIDTH, height: HEIGHT } } );
	const page    = await ctx.newPage();

	await login( page );

	// ── screenshot-1: Settings page ──────────────────────────────────────────
	await page.goto( `${ WP }/wp-admin/options-general.php?page=docrenders`, { waitUntil: 'domcontentloaded' } );
	await page.waitForSelector( '#docrenders-settings-form, .wrap h1', { timeout: 15000 } );
	// Small pause so the usage meter (fetched via XHR) can populate
	await page.waitForTimeout( 2000 );
	await page.screenshot( { path: path.join( ASSETS, 'screenshot-1.png' ), fullPage: false } );
	console.log( 'screenshot-1 saved' );

	// ── screenshot-2: Gutenberg editor with PDF Download Button block ────────
	// Find the post ID of "Getting Started with DocRenders"
	const postResp = await page.request.get(
		`${ WP }/wp-json/wp/v2/posts?search=Getting+Started+with+DocRenders&per_page=1`,
		{ headers: { Authorization: 'Basic ' + Buffer.from( `${ USER }:${ PASS }` ).toString( 'base64' ) } }
	);
	const posts  = await postResp.json();
	const postId = posts[0]?.id ?? 11;

	await page.goto( `${ WP }/wp-admin/post.php?post=${ postId }&action=edit`, { waitUntil: 'domcontentloaded' } );
	// Editor content is in a blob iframe — wait for it
	const editorFrame = page.frameLocator( 'iframe' ).first();
	await editorFrame.locator( '.editor-styles-wrapper' ).waitFor( { timeout: 30000 } );
	await page.waitForTimeout( 2000 );

	// Dismiss "Welcome to the block editor" modal if present
	const welcomeClose = page.locator( 'button[aria-label="Close"]' ).first();
	if ( await welcomeClose.isVisible().catch( () => false ) ) {
		await welcomeClose.click();
	}

	// Scroll the editor to the bottom so the PDF button block is visible
	const lastBlock = editorFrame.locator( '.wp-block' ).last();
	await lastBlock.scrollIntoViewIfNeeded();
	await page.waitForTimeout( 500 );
	await page.screenshot( { path: path.join( ASSETS, 'screenshot-2.png' ), fullPage: false } );
	console.log( 'screenshot-2 saved' );

	// ── screenshot-3: Front-end post with Download PDF button ────────────────
	// Temporarily set placement to after_content so button shows on front end
	await page.request.post(
		`${ WP }/wp-admin/options.php`,
		{
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				Referer: `${ WP }/wp-admin/options-general.php?page=docrenders`,
			},
			form: {
				option_page:                    'docrenders',
				action:                         'update',
				_wp_http_referer:               '/wp-admin/options-general.php?page=docrenders',
				docrenders_button_placement:    'after_content',
				'docrenders_post_types[]':      'post',
				docrenders_button_label:        'Download PDF',
			},
		}
	);
	await page.goto( `${ WP }/?p=${ postId }` );
	await page.waitForLoadState( 'networkidle' );
	await page.waitForTimeout( 800 );
	// Scroll button into view
	const btn = page.locator( '.docrenders-pdf-btn' ).first();
	if ( await btn.isVisible().catch( () => false ) ) {
		await btn.scrollIntoViewIfNeeded();
	}
	await page.screenshot( { path: path.join( ASSETS, 'screenshot-3.png' ), fullPage: false } );
	console.log( 'screenshot-3 saved' );

	// ── screenshot-4: Example PDF rendered in the browser ────────────────────
	// Click the Download PDF button, which triggers a JS fetch that returns a
	// PDF blob. We intercept the response to get the PDF URL and open it.
	const pdfPromise = page.waitForResponse(
		r => r.url().includes( 'admin-ajax.php' ) && r.request().method() === 'POST',
		{ timeout: 20000 }
	);

	// The button posts via fetch() — click it
	await page.locator( '.docrenders-pdf-btn' ).first().click();
	try {
		const resp = await pdfPromise;
		if ( resp.ok() ) {
			const body = await resp.body();
			// Write PDF bytes to a temp file and open it via data URL
			const b64  = body.toString( 'base64' );
			await page.goto( `data:application/pdf;base64,${ b64 }` );
			await page.waitForTimeout( 1500 );
			await page.screenshot( { path: path.join( ASSETS, 'screenshot-4.png' ), fullPage: false } );
			console.log( 'screenshot-4 saved (PDF rendered in browser)' );
		} else {
			throw new Error( `PDF response ${ resp.status() }` );
		}
	} catch ( err ) {
		// Fallback: screenshot a styled "PDF preview" page instead
		console.warn( 'Could not capture live PDF, using styled fallback:', err.message );
		await page.setContent( `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Example PDF</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; background: #525659; display: flex; justify-content: center; padding: 32px 0; font-family: Georgia, serif; }
  .page { width: 780px; background: #fff; padding: 64px 72px 56px; box-shadow: 0 4px 24px rgba(0,0,0,.45); }
  h1 { font-size: 30px; margin: 0 0 4px; color: #111; font-weight: bold; }
  h2 { font-size: 18px; margin: 28px 0 6px; color: #222; }
  h3 { font-size: 15px; margin: 20px 0 4px; color: #333; }
  p { font-size: 14px; line-height: 1.8; color: #333; margin: 0 0 12px; }
  ul { font-size: 14px; line-height: 1.8; color: #333; margin: 0 0 12px; padding-left: 24px; }
  .rule { border: none; border-top: 1px solid #ddd; margin: 32px 0 8px; }
  .footer { font-size: 10px; color: #aaa; text-align: center; }
  .url { font-size: 10px; color: #888; margin-bottom: 24px; }
</style>
</head>
<body>
<div class="page">
  <p class="url">https://example.com/getting-started-with-docrenders/</p>
  <h1>Getting Started with DocRenders</h1>
  <h2>Introduction</h2>
  <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.</p>
  <h3>Key Features</h3>
  <ul>
    <li>One-click PDF generation</li>
    <li>Clean, print-ready output</li>
    <li>Works with any post or page</li>
    <li>Powered by the DocRenders API</li>
  </ul>
  <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
  <hr class="rule">
  <p class="footer">PDF generated · docrenders.com</p>
</div>
</body>
</html>` );
		await page.evaluate( () => window.scrollTo( 0, 0 ) );
		await page.waitForTimeout( 400 );
		await page.screenshot( { path: path.join( ASSETS, 'screenshot-4.png' ), fullPage: false } );
		console.log( 'screenshot-4 saved (styled fallback)' );
	}

	await browser.close();
	console.log( 'Done.' );
} )();
