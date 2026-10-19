// Adds background-audio mode to the iOS app after `cap add ios`.
// Lets sound keep playing when the app goes to the background (where iOS allows it for web audio).
import { readFileSync, writeFileSync, existsSync } from 'node:fs';

const plist = 'ios/App/App/Info.plist';
if (!existsSync(plist)) {
  console.error(`Not found: ${plist}. Run "npx cap add ios" first.`);
  process.exit(1);
}
let xml = readFileSync(plist, 'utf8');
if (xml.includes('UIBackgroundModes')) {
  console.log('Info.plist already has UIBackgroundModes, nothing to do.');
} else {
  xml = xml.replace(
    /<\/dict>\s*<\/plist>\s*$/,
    '\t<key>UIBackgroundModes</key>\n\t<array>\n\t\t<string>audio</string>\n\t</array>\n</dict>\n</plist>\n'
  );
  writeFileSync(plist, xml);
  console.log('Added UIBackgroundModes: audio to Info.plist');
}
