/**
 * The Forge API
 * Code adapted from The Forge with its author permission
 * Original code written by  Youness Alaoui <admin@forge-vtt.com>
 */
class Forge {
    regions = {
        "DO-NYC3": { id: "DO-NYC3", name: "North America (East)", domain: "forge-vtt.com", available: true },
        "DO-AMS3": { id: "DO-AMS3", name: "Europe", domain: "eu.forge-vtt.com", available: true },
        "DO-SGP1": { id: "DO-SGP1", name: "Asia & Oceania", domain: "as.forge-vtt.com", available: true }
    }

    constructor(token) {
        this.token = token;
        this.domain = this.regions["DO-NYC3"].domain;
        if (token != "")
            this.init();
    }

    showForgeMessage(type = "error", message) {
        jQuery("#forge-message").removeClass().addClass(type).css("display", "block").html(message);
    }

    async init() {
        this.profile = await this.api("profile");
        this.domain = this.regions[this.profile.region].domain;
        this.customPackages = [];
        const [systems, modules, entitlements] = await Promise.all([
            this.api("data/systems"),
            this.api("data/modules"),
            this.api("profile/entitlements")
        ]);
        if (systems.error || modules.error || entitlements.error) {
            return;
        }

        systems.forEach(function (part, index, theArray) {
            theArray[index].type = "system";
            theArray[index].installed = String(part.version);
        });

        modules.forEach(function (part, index, theArray) {
            theArray[index].type = "module";
            theArray[index].installed = String(part.version);
        });

        this.installedSystems = systems;
        this.installedModules = modules;
        this.entitlements = entitlements;
        this.packages = systems.concat(modules);

        this.pkg = this.packages.find(p => p.name === singlePackage.package.name);
        if (this.pkg) {
            if (singlePackage.package.latest == this.pkg.installed) { //already installed and uptodate
                jQuery("#forge-download").attr("disabled", "disabled").find("span").text("Already installed!");
                this.showForgeMessage("info", `Don't forget to <a href="https://forge-vtt.com/setup" target="_blank">restart</a> your server if you can't see this package.`);
            } else { //installed to a different version than latest, guessing "update"
                jQuery("#forge-download").find("span").text("Update available");
            }
        } else {
            this.pkg = singlePackage.package;
        }
        jQuery("#forge-download-container").css("display", "block");
    }
    async installPackageDependencies(dependencies) {
        const missing = dependencies.filter(dep => !this.packages.find(p => p.installed && p.type === (dep.type || 'module') && p.name === dep.name));
        if (missing.length === 0) return 0;
        const missingNames = missing.map(dep => {
            const pkg = this.packages.find(p => p.type === (dep.type || 'module') && p.name === dep.name);
            dep.pkg = pkg;
            return pkg ? pkg.title : dep.name;
        });
        let installed = 0;
        for (const dep of missing) {
            if (dep.manifest) {
                const manifest = await this.installPackageFromManifest(dep.type || 'module', dep.name, dep.manifest);
                if (manifest)
                    installed++;
            } else if (dep.pkg && this.installPackage(dep.pkg)) {
                installed++;
            }
        }
        this.showForgeMessage('info', `Installed jQuery{installed} package dependencies: jQuery{missingNames.join(", ")}`);
        return installed;
    }
    async installPackageFromManifest(type, name, manifest) {
        const response = await this.api(`package/install`, { type: type || 'module', name, manifest });
        if (response.error) {
            this.showForgeMessage('error', `Failed to install ${type} : ${response.error.replace("Terms of Service", "<a href='https://forge-vtt.com/tos' target='_blank'>Terms of Service</a>")}`);
        } else if (name && response.manifest.name !== name) {
            this.showForgeMessage('error', `Failed to install ${type} : Wrong package archive installed`);
        } else {
            if (response.manifest.dependencies) {
                await this.installPackageDependencies(response.manifest.dependencies);
            }
            return response.manifest;
        }
        return null;
    }
    async installPackage(pkg) {
        if (!pkg)
            pkg = this.pkg;
        let success = false;
        pkg.processing = true;
        if (pkg.custom) {
            const manifest = await this.installPackageFromManifest(pkg.type, pkg.name, pkg.manifest);
            if (manifest) {
                success = true;
            }
            pkg.processing = false;
            return success;
        }
        if (["module", "system"].includes(pkg.type)) {
            const response = await this.api(`data/${pkg.type}s/install`, { name: pkg.name });
            if (response.error) {
                const verb = pkg.installed ? "update" : "install";
                this.showForgeMessage('error', `Failed to ${verb} ${pkg.type} : ${response.error}`);
            } else if (response.installed) {
                success = true;
                // Some use Number as a version
                pkg.installed = String(response.version);
                pkg.secret = response.secret;
                if (response.dependencies) {
                    await this.installPackageDependencies(response.dependencies);
                }
            }
        } else if (pkg.type === "world") {
            this.showForgeMessage('error', `World installation is not yet supported from Foundry Hub.`);
            //TO DO LATER
            /*const response = await new Promise(resolve => {
                this.installWorld = pkg;
                this.installWorldResolve = resolve;
                this.installWorldVisible = true;
            });
            this.installWorldResolve = null;
            if (response && response.system) {
                const system = this.allPackages.find(p => p.type === "system" && p.name === response.system);
                if (system && !system.installed) {
                    await this.installPackage(system);
                    UI.alert('info', `Installed required Game System: ${system.title}`);
                }
            }
            success = !!response;
            */
        }
        if(success)
            await this.idleGames();
        pkg.processing = false;
        return success;
    }

    async idleGames() {
        const idle = await this.api(`game/idle`, {});
        if (!idle.success)
            this.showForgeMessage('info', `You have currently in use games. You will need to <a href="https://forge-vtt.com/setup" target="_blank">restart</a> the servers for the updated packages to become available.`, true);
    }

    /**
     * Send an API request
     * @param {String} endpoint               API endpoint
     * @param {FormData} formData             Form Data to send. POST if set, GET otherwise
     * @param {Object} options                Options
     * @param {String} options.method         Override API request method to use
     * @param {Function} options.progress     Progress report. function(step, percent)
     *                                        Step 0: Request started
     *                                        Step 1: Uploading request
     *                                        Step 2: Downloading response
     *                                        Step 3: Request completed
     */
    async api(endpoint, formData = null, { method, json = true } = {}) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.withCredentials = true;

            method = method || (formData ? 'POST' : 'GET');
            const url = `https://${this.domain}/api/${endpoint}`;
            xhr.open(method, url);
            xhr.setRequestHeader("Authorization", "Bearer " + this.token);
            xhr.responseType = 'text';

            xhr.onreadystatechange = () => {
                if (xhr.readyState !== 4) return;

                let response = { code: xhr.status, error: xhr.statusText || `Error ${xhr.status}` }
                try {
                    response = JSON.parse(xhr.response);
                } catch (err) { }
                resolve(response);
            };
            xhr.onerror = (err) => {
                resolve({ code: 500, error: err.message });
            };
            if (json) {
                xhr.setRequestHeader('Content-type', 'application/json; charset=utf-8');
                formData = JSON.stringify(formData);
            }
            xhr.send(formData);
        });
    }
}