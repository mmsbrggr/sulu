define(["services/sulumedia/collection-manager","services/sulumedia/user-settings-manager","services/sulumedia/media-router","services/sulumedia/overlay-manager","sulusecurity/services/security-checker"],function(a,b,c,d,e){"use strict";var f={};return{header:function(){var a={edit:{options:{title:"sulu.header.edit-collection",dropdownItems:{}}}};return e.hasPermission(this.data,"edit")&&!this.data.locked&&(a.edit.options.dropdownItems.editCollection={},a.moveCollection={}),e.hasPermission(this.data,"delete")&&!this.data.locked&&(a.edit.options.dropdownItems.deleteCollection={}),this.sandbox.util.isEmpty(a.edit.options.dropdownItems)&&delete a.edit,e.hasPermission(this.data,"security")&&!this.data.locked&&(a.permissionSettings={}),{title:this.data.title,noBack:!0,tabs:{url:"/admin/content-navigations?alias=media",componentOptions:{values:this.data}},toolbar:{buttons:a,languageChanger:{url:"/admin/api/localizations",resultKey:"localizations",titleAttribute:"localization",preSelected:b.getMediaLocale()}}}},loadComponentData:function(){return a.loadOrNew(this.options.id,b.getMediaLocale())},initialize:function(){this.options=this.sandbox.util.extend(!0,{},f,this.options),b.setLastVisitedCollection(this.data.id),this.sandbox.emit("husky.navigation.select-id","collections-edit",{dataNavigation:{url:"/admin/api/collections/"+this.data.id+"?depth=1&sortBy=title"}}),this.sandbox.emit("husky.data-navigation.collections.set-locale",b.getMediaLocale()),this.updateDataNavigationAddButton(),this.bindCustomEvents(),this.bindOverlayEvents(),this.bindManagerEvents()},updateDataNavigationAddButton:function(){e.hasPermission(this.data,"add")&&!this.data.locked?this.sandbox.emit("husky.data-navigation.collections.add-button.show"):this.sandbox.emit("husky.data-navigation.collections.add-button.hide")},bindCustomEvents:function(){this.sandbox.on("husky.data-navigation.collections.initialized",this.updateDataNavigationAddButton.bind(this)),this.sandbox.on("sulu.header.language-changed",function(a){b.setMediaLocale(a.id),c.toCollection(this.data.id)}.bind(this)),this.sandbox.on("sulu.toolbar.edit-collection",function(){d.startEditCollectionOverlay.call(this,this.data.id,b.getMediaLocale())}.bind(this)),this.sandbox.on("sulu.toolbar.move-collection",function(){d.startMoveCollectionOverlay.call(this,this.data.id,b.getMediaLocale())}.bind(this)),this.sandbox.on("sulu.toolbar.delete-collection",this.deleteCollection.bind(this)),this.sandbox.on("sulu.toolbar.collection-permissions",function(){d.startPermissionSettingsOverlay.call(this,this.data.id,"Sulu\\Bundle\\MediaBundle\\Entity\\Collection","sulu.media.collections")}.bind(this)),this.sandbox.on("sulu.medias.collection.get-data",function(a){a(this.sandbox.util.deepCopy(this.data))}.bind(this))},bindOverlayEvents:function(){this.sandbox.on("sulu.collection-select.move-collection.selected",this.moveCollection.bind(this))},bindManagerEvents:function(){this.sandbox.on("sulu.medias.collection.saved",function(a,c){c.locale&&c.locale!==b.getMediaLocale()||(this.data=c,this.sandbox.emit("sulu.header.saved",this.data))}.bind(this)),this.sandbox.on("sulu.medias.collection.deleted",function(){var a=this.data._embedded.parent?this.data._embedded.parent.id:null;this.sandbox.emit("husky.data-navigation.collections.reload"),this.sandbox.emit("husky.data-navigation.collections.select",a),c.toCollection(a)}.bind(this))},deleteCollection:function(){this.sandbox.sulu.showDeleteDialog(function(b){b&&a["delete"](this.data.id)}.bind(this),"sulu.header.delete-collection")},moveCollection:function(b){a.move(this.data.id,b.id).then(function(){this.sandbox.emit("husky.data-navigation.collections.reload"),this.sandbox.emit("husky.data-navigation.collections.select",b.id),c.toCollection(this.data.id)}.bind(this))}}});