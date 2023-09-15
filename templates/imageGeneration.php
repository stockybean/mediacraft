<form id="image-tools-wrapper">
    <table class="form-table">
        <tr valign="top">
            <th scope="row">Midjourney Generate<br><span id="mj-credits" style="font-size: 0.75rem; margin-top: 0.5rem; display: inline-block; opacity: 0.7; font-weight: lighter;">
                    <?php echo get_option('genolve_credits'); ?></span></th>
            <td>
                <div style="margin-bottom: 1rem;">
                    <textarea id="mj-prompt-input" name="mj-prompt"></textarea>
                    <p style="font-style: italic; opacity: 0.6;">Prompt</p>
                </div>
                <div style="margin-bottom: 1rem;">
                    <input type="text" id="mj-negative-prompt-input" name="mj-negative-prompt" />
                    <p style="font-style: italic; opacity: 0.6;">Negative prompt</p>
                </div>
                <div style="margin-bottom: 1rem;">
                    <select id="mj-keywords-select" class="multiselect-container" multiple="multiple" tabindex="-1">
                        <optgroup label="general&nbsp;styles/popular" data-max-options="5">
                            <option value="futuristic">futuristic</option>
                            <option value="retro">retro</option>
                            <option value="concept art">concept art</option>
                            <option value="digital art">digital art</option>
                            <option value="cinematic">cinematic</option>
                            <option value="award winning">award winning</option>
                            <option value="intricate detail">intricate detail</option>
                        </optgroup>
                        <optgroup label="moods" data-max-options="5">
                            <option value="atmospheric">atmospheric</option>
                            <option value="vibrant">vibrant</option>
                            <option value="captivating">captivating</option>
                            <option value="fun">fun</option>
                            <option value="playful">playful</option>
                            <option value="mysterious">mysterious</option>
                            <option value="majestic">majestic</option>
                            <option value="luxurious">luxurious</option>
                            <option value="urban">urban</option>
                            <option value="ethereal">ethereal</option>
                            <option value="epic">epic</option>
                            <option value="occult">occult</option>
                            <option value="surreal">surreal</option>
                            <option value="scary">scary</option>
                            <option value="dark">dark</option>
                            <option value="psychedelic">psychedelic</option>
                            <option value="moody">moody</option>
                            <option value="edgy">edgy</option>
                        </optgroup>
                        <optgroup label="prompt hacks" data-max-options="5">
                            <option value="trending on Artstation">trending on Artstation</option>
                            <option value="Deviantart">Deviantart</option>
                            <option value="Dribbble">Dribbble</option>
                            <option value="Our Art Corner">Our Art Corner</option>
                            <option value="Pixiv">Pixiv</option>
                            <option value="BioShock Infinite">BioShock Infinite</option>
                            <option value="Dark Souls">Dark Souls</option>
                            <option value="Final Fantasy">Final Fantasy</option>
                            <option value="Legend of Zelda">Legend of Zelda</option>
                            <option value="No Mans Sky">No Mans Sky</option>
                            <option value="Skyrim">Skyrim</option>
                        </optgroup>
                        <optgroup label="movie styles" data-max-options="5">
                            <option value="Pixar style">Pixar style</option>
                            <option value="Disney style">Disney style</option>
                            <option value="Lord of the Rings">Lord of the Rings</option>
                            <option value="Wes Anderson">Wes Anderson</option>
                            <option value="Tim Burton">Tim Burton</option>
                            <option value="Stanley Kubrick">Stanley Kubrick</option>
                            <option value="David Lynch">David Lynch</option>
                            <option value="Christopher Nolan">Christopher Nolan</option>
                            <option value="Ridley Scott">Ridley Scott</option>
                            <option value="Guillermo del Toro">Guillermo del Toro</option>
                            <option value="Wachowski">Wachowski</option>
                        </optgroup>
                        <optgroup label="photographic styles" data-max-options="5">
                            <option value="fish-eye lens">fish-eye lens</option>
                            <option value="35mm">35mm</option>
                            <option value="85mm">85mm</option>
                            <option value="25mm">25mm</option>
                            <option value="macro shot">macro shot</option>
                            <option value="panoramic shot">panoramic shot</option>
                            <option value="photo realistic">photo realistic</option>
                            <option value="bokeh">bokeh</option>
                            <option value="motion blur">motion blur</option>
                            <option value="vignetting">vignetting</option>
                        </optgroup>
                        <optgroup label="lighting" data-max-options="5">
                            <option value="backlit">backlit</option>
                            <option value="soft lighting">soft lighting</option>
                            <option value="golden hour">golden hour</option>
                            <option value="blue hour">blue hour</option>
                            <option value="rembrandt light">rembrandt light</option>
                            <option value="sun beams">sun beams</option>
                            <option value="spot lighting">spot lighting</option>
                            <option value="lense flare">lense flare</option>
                            <option value="volumetric lighting">volumetric lighting</option>
                            <option value="dramatic lighting">dramatic lighting</option>
                            <option value="rim lighting">rim lighting</option>
                        </optgroup>
                        <optgroup label="painting styles" data-max-options="5">
                            <option value="as a Van Gogh painting">as a Van Gogh painting</option>
                            <option value="as a Pablo Picasso painting">as a Pablo Picasso painting</option>
                            <option value="as a Claude Monet painting">as a Claude Monet painting</option>
                            <option value="as a Piet Mondrian painting">as a Piet Mondrian painting</option>
                            <option value="as a traditional Chinese painting">as a traditional Chinese painting</option>
                            <option value="oil painting">oil painting</option>
                            <option value="acrylic painting">acrylic painting</option>
                            <option value="watercolor">watercolor</option>
                            <option value="spray paint">spray paint</option>
                        </optgroup>
                        <optgroup label="art period styles" data-max-options="5">
                            <option value="art deco style">art deco style</option>
                            <option value="art nouveau style">art nouveau style</option>
                            <option value="avant-garde style">avant-garde style</option>
                            <option value="baroque style">baroque style</option>
                            <option value="bauhaus style">bauhaus style</option>
                            <option value="CoBrA style">CoBrA style</option>
                            <option value="cubism style">cubism style</option>
                            <option value="dadaism style">dadaism style</option>
                            <option value="expressionism style">expressionism style</option>
                            <option value="impressionism style">impressionism style</option>
                            <option value="cartoon style">cartoon style</option>
                            <option value="comic book style">comic book style</option>
                            <option value="anime style">anime style</option>
                            <option value="graffiti style">graffiti style</option>
                        </optgroup>
                        <optgroup label="render engines" data-max-options="5">
                            <option value="Unreal Engine">Unreal Engine</option>
                            <option value="Unity">Unity</option>
                            <option value="Gamecore 3d">Gamecore 3d</option>
                            <option value="Blender 3d">Blender 3d</option>
                            <option value="Cinema 4d">Cinema 4d</option>
                            <option value="Maya">Maya</option>
                            <option value="4k">4k</option>
                            <option value="8k">8k</option>
                            <option value="ray tracing">ray tracing</option>
                            <option value="Octane render">Octane render</option>
                            <option value="Indigo render">Indigo render</option>
                            <option value="K-Cycles">K-Cycles</option>
                            <option value="LuxCoreRender">LuxCoreRender</option>
                            <option value="V-Ray">V-Ray</option>
                        </optgroup>
                        <optgroup label="vector styles" data-max-options="5">
                            <option value="illustrator style">illustrator style</option>
                            <option value="as a vector graphic">as a vector graphic</option>
                            <option value="ui">ui</option>
                            <option value="ux">ux</option>
                            <option value="flat icon">flat icon</option>
                            <option value="corporate memphis style">corporate memphis style</option>
                            <option value="as a sprite sheet">as a sprite sheet</option>
                        </optgroup>
                        <optgroup label="3d art styles" data-max-options="5">
                            <option value="as a statue of bronze">as a statue of bronze</option>
                            <option value="as a statue of marble">as a statue of marble</option>
                            <option value="crafted from clay">crafted from clay</option>
                            <option value="as a 3D model">as a 3D model</option>
                            <option value="as origami">as origami</option>
                        </optgroup>
                        <optgroup label="no background" data-max-options="5">
                            <option value="green screen">green screen</option>
                            <option value="clean background">clean background</option>
                            <option value="clear background">clear background</option>
                            <option value="no background">no background</option>
                            <option value="product shot">product shot</option>
                        </optgroup>
                        <optgroup label="midjourney&nbsp;special&nbsp;options" data-max-options="5">
                            <option value="--tile">--tile</option>
                            <option value="--niji">--niji</option>
                            <option value="--quality 2">--quality 2</option>
                            <option value="--stylize 1000">--stylize 1000</option>
                            <option value="--chaos 70">--chaos 70</option>
                        </optgroup>
                    </select>
                    <p style="font-style: italic; opacity: 0.6;">Negative prompt</p>
                </div>
                <div style="margin-bottom: 1rem;">
                    <input type="text" id="mj-ar-input" name="mj-ar" value="2:1" />
                    <p style="font-style: italic; opacity: 0.6;">Aspect Ratio (2:1, 1:1, 1:2)</p>
                </div>
                <button id="generate-image-submit" class="button" data-action="generate">Generate!</button>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Generated Images</th>
            <td style="position: relative;">
                <div id="generation-preview" class="wrapper-card" style="position: relative;"></div>
            </td>
        </tr>
        <tr>
            <td>
                <hr style="margin: 1rem 0;">
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">PNG to JPG</th>
            <td>
                <input type="text" id="img-conversion-url-input" name="img-conversion-url" />
                <button id="convert-image-submit" class="button" data-action="convert-url">Convert!</button>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Upscale An Image</th>
            <td>
                <input type="text" id="img-upscale-url-input" name="img-upscale-url" />
                <button id="upscale-image-submit" class="button" data-action="upscale-url" style="height: ">Upscale!</button>
            </td>
        </tr>
    </table>
</form>