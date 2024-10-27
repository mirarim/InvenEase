/**
 * Please add this file to /libs/js/reports.js
 */

class PageElement {
    constructor(name){
        this.name = name
    }
    get(){
        return document.getElementById(
            this.name
        )
    }
    nullify(){
        this.get().value = null
    }
    clear(){
        this.get().innerHTML = ''
    }
    enable(){
        this.get().disabled = false 
    }
    disable(){
        this.get().disabled = true
    }
    event(name, callback){
        this.get().addEventListener(
            name,
            callback
        )
    }
}

const DescriptionTextBox = new PageElement('damage_description')
const SubmitButton = new PageElement('submit')
const ProductIdInput = new PageElement('product_id')
const SearchBar = new PageElement('search_product_bar')
const ResultsBox = new PageElement('search_result')
const CancelButton = new PageElement('cancel')

const areInputsValid = () => {
    const description = DescriptionTextBox.get().value
    const productid   = ProductIdInput.get().value
    if (description.trim() === '' 
        || productid === undefined 
        || productid === null 
        || productid.trim() === ''
    ) {
        return false
    }
    return true
}
const getPagePath = () => {
    return `${location.origin}${location.pathname}`
}
const shouldActivateButton = () => {
    if (!areInputsValid()) {
        SubmitButton.disable()
        return
    } 
    SubmitButton.enable()
}
DescriptionTextBox.event('keyup', shouldActivateButton)
SearchBar.event('keyup',()=>{
    const value = SearchBar.get().value
    if (value.trim() === '') {
        ResultsBox.clear()
        ProductIdInput.nullify()
        shouldActivateButton()
        return
    }
    const uri = `${getPagePath()}?view=search&name=${value}`
    $.get(uri).then(response => {
        if (response.length !== undefined 
            && response.length === 0
        ) {
            ProductIdInput.nullify()
        }
        let html = ''
        for (const name in response) {
            html += `<div class="search-product-class" 
                data-product-id="${response[name]}">${name}
            </div>`
        }
        ResultsBox.get().innerHTML = html
    })
})
SearchBar.event('change',()=>{
    ResultsBox.event('click',event=>{
        const element   = event.target 
        const productid = element.dataset.productId 
        const name      = element.innerText.trim()
        SearchBar.get().value = name
        ResultsBox.clear()
        ProductIdInput.get().value = productid
        shouldActivateButton()
    })
})
CancelButton.event('click',()=>{
    location.href = getPagePath()
})
document.getElementById('create_report_form').addEventListener('submit', event => {
    event.preventDefault()
    const description   = DescriptionTextBox.get().value
    const productId = ProductIdInput.get().value
    if (!areInputsValid()) return
    const uri = `${getPagePath()}`
    $.ajax({
        url: uri,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({productId, description}),
        success: response =>{
            location.href = getPagePath()
        },
        error:()=>{
            
        }
    })
})